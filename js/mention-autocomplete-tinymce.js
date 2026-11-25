/**
 * mention-autocomplete-tinymce.js
 *
 * @mention autocomplete for HESK admin panel — TinyMCE 7 / Rich Text mode.
 *
 * Detection strategy — key-agnostic via getQueryFromCursor():
 *   Uses editor.on('input') (fires after content is committed to the DOM
 *   in TinyMCE 7) and reads the text node backwards from the caret to find
 *   @query.  No e.key checks — works on all keyboard layouts.
 *
 * Span (best-effort visual):
 *   After @ is detected, tryInjectSpan() runs via requestAnimationFrame.
 *   If TinyMCE 7 normalises it away the dropdown still works via text-position.
 *
 * Chip insertion (selectMention):
 *   Primary  — selects in-progress span and replaces.
 *   Fallback — uses getQueryFromCursor() range if span was removed.
 *
 * Requires window.HeskMention.tinyMceId.
 */

(function () {
    var cfg           = window.HeskMention || {};
    var ticketId      = cfg.ticketId    || 0;
    var category      = cfg.category    || 0;
    var tinyMceId     = cfg.tinyMceId   || '';
    var searchingText = cfg.searchingText;

    if (!tinyMceId) return;

    var activeEditorInstance = null;
    var activeState          = null;
    var mentionSequence      = 0;

    function createState() {
        return {
            active:          false,
            span:            null,
            ignoreSelChange: false,
            results:         [],
            activeIndex:     -1,
            xhr:             null,
            suppressInput:   false
        };
    }

    /* ------------------------------------------------------------------
     * PRIMARY DETECTION: scan text node backwards from caret for @query.
     * Returns { query, node, atOffset, cursorOffset } or null.
     * Works whether or not the in-progress span is present.
     * ------------------------------------------------------------------ */
    function getQueryFromCursor(editor) {
        var win = editor.getWin();
        var sel = win.getSelection();
        if (!sel || !sel.rangeCount || !sel.getRangeAt(0).collapsed) return null;

        var range  = sel.getRangeAt(0);
        var node   = range.startContainer;
        var offset = range.startOffset;

        if (!node || node.nodeType !== 3) return null;

        var text  = node.textContent.substring(0, offset);
        var atIdx = text.lastIndexOf('@');
        if (atIdx === -1) return null;

        var query = text.substring(atIdx + 1);
        if (/[\s\n\r]/.test(query)) return null; /* space = no longer in mention */

        return { query: query, node: node, atOffset: atIdx, cursorOffset: offset };
    }

    /* ------------------------------------------------------------------
     * DROPDOWN
     * ------------------------------------------------------------------ */
    function getDropdown() {
        var $dd = $('#mention-dropdown');
        if (!$dd.length) $dd = $('<ul id="mention-dropdown" role="listbox"></ul>').appendTo('body');
        return $dd;
    }

    /* Caret position in iframe → page coordinates.
       Uses editor.iframeElement (TinyMCE 7 direct property) with a fallback
       to querySelector so it works on older TinyMCE 6 builds as well. */
    function getTinyMceCaretRect(editor) {
        var iframeEl = editor.iframeElement ||
                       (editor.getContainer && editor.getContainer().querySelector('iframe'));
        if (!iframeEl) return null;

        var iframeRect = iframeEl.getBoundingClientRect();
        var win = editor.getWin();
        var doc = editor.getDoc();
        var sel = win.getSelection();
        if (!sel || !sel.rangeCount) return null;

        var range = sel.getRangeAt(0).cloneRange();
        range.collapse(true);
        var innerRect = range.getBoundingClientRect();

        return {
            bottom: iframeRect.top  + window.scrollY + innerRect.bottom,
            left:   iframeRect.left + window.scrollX + innerRect.left
        };
    }

    function positionDropdown(editor) {
        var rect = getTinyMceCaretRect(editor);
        if (!rect) return;
        getDropdown().css({ top: rect.bottom + 4, left: rect.left });
    }

    function setActive(idx) {
        if (!activeState) return;
        var $items = getDropdown().children('li');
        $items.removeClass('mention-active');
        if (idx >= 0 && idx < $items.length) $items.eq(idx).addClass('mention-active');
        activeState.activeIndex = idx;
    }

    function renderItem(user, editor, state) {
        var $li = $('<li role="option"></li>');
        $li.html(
            '<span class="mention-user">@' + $('<b>').text(user.name).html() + '</span>' +
            '<span class="mention-name">' + $('<b>').text(user.email + ' (#' + user.id + ')').html() + '</span>'
        );
        $li.on('mousedown', function (e) { e.preventDefault(); selectMention(user, editor, state); });
        return $li;
    }

    function showResults(results, editor, state) {
        var $dd = getDropdown();
        $dd.empty();
        state.results     = results;
        state.activeIndex = -1;
        if (!results.length) { $dd.hide(); return; }
        results.forEach(function (user) { $dd.append(renderItem(user, editor, state)); });
        positionDropdown(editor);
        $dd.show();
    }

    function doSearch(query, editor, state) {
        if (state.xhr) state.xhr.abort();
        state.results = [];
        state.activeIndex = -1;
        getDropdown()
            .data('mention-editor', editor)
            .empty()
            .append($('<li class="mention-loading"><span class="mention-spinner"></span></li>').append(document.createTextNode(' ' + searchingText)));
        positionDropdown(editor);
        getDropdown().show();
        state.xhr = $.ajax({
            url:      'ajax/search_staff.php',
            type:     'GET',
            data:     { query: query, ticket_id: ticketId, category: category },
            dataType: 'json',
            success:  function (data) { state.xhr = null; if (state.active && getDropdown().data('mention-editor') === editor) showResults(Array.isArray(data) ? data : [], editor, state); },
            error:    function (xhr, status) { if (status !== 'abort') if ($('#mention-dropdown').data('mention-editor') === editor) $('#mention-dropdown').hide().empty(); }
        });
    }

    /* ------------------------------------------------------------------
     * CANCEL — Escape / blur / selectionChange.
     * Keeps the @query text as plain text; does NOT fire an input event.
     * ------------------------------------------------------------------ */
    function cancelMention(editor, state) {
        if (!state.active && !state.span) return;
        var span = state.span;
        state.active          = false;
        state.span            = null;
        state.ignoreSelChange = false;
        state.results         = [];
        state.activeIndex     = -1;
        if (state.xhr) { state.xhr.abort(); state.xhr = null; }
        if ($('#mention-dropdown').data('mention-editor') === editor) $('#mention-dropdown').hide().empty();

        if (span && span.parentNode) {
            /* Keep the exact caret/selection while removing the query wrapper. */
            var bookmark = editor.selection.getBookmark();
            editor.dom.remove(span, true);
            editor.selection.moveToBookmark(bookmark);
        }
    }

    /* ------------------------------------------------------------------
     * SELECT MENTION — replace in-progress span (or @query text) with chip
     * ------------------------------------------------------------------ */
    function selectMention(user, editor, state) {
        if (!user || !state.active) return;

        var doc  = editor.getDoc();
        var span = state.span;

        /* Clear state BEFORE setRng — setRng fires SelectionChange */
        state.active          = false;
        state.span            = null;
        state.ignoreSelChange = false;
        state.results         = [];
        state.activeIndex     = -1;
        if (state.xhr) { state.xhr.abort(); state.xhr = null; }
        if ($('#mention-dropdown').data('mention-editor') === editor) $('#mention-dropdown').hide().empty();

        editor.undoManager.add();
        var chip = doc.createElement('span');
        chip.className = 'mention-chip';
        chip.setAttribute('contenteditable', 'false');
        chip.id = 'hesk-mention-' + user.id + '-' + Date.now().toString(36) + '-' + (++mentionSequence);
        chip.setAttribute('title', user.name + (user.email ? ' <' + user.email + '>' : '') + ' (#' + user.id + ')');
        chip.textContent = '@' + user.name;

        if (span && span.parentNode) {
            /* Direct DOM replacement — avoids TinyMCE inserting the chip
             * *inside* the mention-in-progress span (which leaves its blue
             * background visible behind the chip). */
            var space = doc.createTextNode('\u00a0');
            span.parentNode.insertBefore(chip,  span);
            span.parentNode.insertBefore(space, span);
            span.parentNode.removeChild(span);

            /* Move cursor after the trailing space */
            var cur = doc.createRange();
            cur.setStartAfter(space);
            cur.collapse(true);
            editor.selection.setRng(cur);
        } else {
            /* No span — select the @query text and replace with chip HTML */
            var info = getQueryFromCursor(editor);
            if (!info) return;
            var replaceRange = doc.createRange();
            replaceRange.setStart(info.node, info.atOffset);
            replaceRange.setEnd(info.node, info.cursorOffset);
            editor.selection.setRng(replaceRange);

            editor.selection.setContent(chip.outerHTML + '\u00a0');
        }

        editor.undoManager.add();
        editor.nodeChanged();
        editor.dispatch('input');
        editor.focus();
    }

    /* ------------------------------------------------------------------
     * TRY INJECT SPAN — best-effort visual; called via requestAnimationFrame
     * ------------------------------------------------------------------ */
    function tryInjectSpan(editor, state) {
        /* A cancelled query may still have a queued animation frame. */
        if (!state.active || (state.span && state.span.parentNode)) return;

        var info = getQueryFromCursor(editor);
        if (!info) return;

        var win = editor.getWin();
        var doc = editor.getDoc();

        var replaceRange = doc.createRange();
        replaceRange.setStart(info.node, info.atOffset);
        replaceRange.setEnd(info.node, info.cursorOffset);

        var span = doc.createElement('span');
        span.className   = 'mention-in-progress';
        span.textContent = '@' + info.query;

        replaceRange.deleteContents();
        var sel = win.getSelection();
        if (!sel || !sel.rangeCount) return;
        sel.getRangeAt(0).insertNode(span);
        if (!span.firstChild || span.firstChild.nodeType !== 3) return;

        var innerRange = doc.createRange();
        innerRange.setStart(span.firstChild, span.firstChild.length);
        innerRange.collapse(true);

        state.span            = span;
        state.ignoreSelChange = true;

        editor.selection.setRng(innerRange);

        setTimeout(function () {
            if (state.span === span) state.ignoreSelChange = false;
        }, 150);
    }

    /* ------------------------------------------------------------------
     * SETUP — attach handlers to a fully-initialised TinyMCE 7 editor
     * ------------------------------------------------------------------ */
    function setupForEditor(editor) {
        var state = createState();
        function restoreMentions() {
            editor.dom.select('span.mention-chip').forEach(function (chip) {
                if (/^hesk-mention-[0-9]+(?:-[a-z0-9]+)*$/i.test(chip.id)) {
                    chip.setAttribute('contenteditable', 'false');
                }
            });
        }
        editor.on('SetContent', restoreMentions);
        restoreMentions();

        /*
         * INPUT — fires after content is committed to the DOM in TinyMCE 7.
         * Key-agnostic: we read the text node rather than checking e.key,
         * so it works on all keyboard layouts and locales.
         *
         *   No active mention → check if cursor is right after a fresh @
         *   Active mention    → re-query with current text after @
         */
        editor.on('input', function () {
            if (cfg.canAssign === false) return;
            if (state.suppressInput) { state.suppressInput = false; return; }
            if (state.active) {
                if (state.span && state.span.parentNode) {
                    /* Span is alive — read query directly from it so that
                     * characters like @ or # inside a name or email are included
                     * in the query instead of being mis-detected as a new trigger. */
                    if (!state.span.textContent) { cancelMention(editor, state); return; }
                    var q = state.span.textContent.substring(1).replace(/\u00a0/g, ' ');
                    doSearch(q, editor, state);
                } else {
                    /* Span was removed by TinyMCE — fall back to text-position scan */
                    var info = getQueryFromCursor(editor);
                    if (!info) { cancelMention(editor, state); return; }
                    doSearch(info.query, editor, state);
                }
                return;
            }

            var info = getQueryFromCursor(editor);
            if (info && info.query === '') {
                /*
                 * Cursor is right after @ with no trailing text — @ was just
                 * typed (or backspace left cursor here, which also triggers).
                 * Only activate if @ is the first character in the node or
                 * immediately preceded by whitespace (prevents email triggers).
                 */
                var nodeText   = info.node.textContent;
                var charBefore = info.atOffset > 0 ? nodeText[info.atOffset - 1] : '';
                if (charBefore === '' || /[\s\u00a0]/.test(charBefore)) {
                    state.active         = true;
                    activeEditorInstance = editor;
                    activeState          = state;
                    doSearch('', editor, state);
                    requestAnimationFrame(function () { tryInjectSpan(editor, state); });
                }
            }
        });

        /* KEYDOWN — cancel, navigation, confirm */
        editor.on('keydown', function (e) {
            if (e.isComposing || e.keyCode === 229) return;
            if (state.active && e.key === 'Escape') {
                cancelMention(editor, state);
                return;
            }
            if (state.active && e.key === 'Backspace') {
                /* Unwrap first, then let the browser delete at the caret. */
                cancelMention(editor, state);
                state.suppressInput = true;
                setTimeout(function () { state.suppressInput = false; }, 0);
                return;
            }

            var $dd = $('#mention-dropdown');
            if (state.active && e.key === 'Enter' &&
                (state.activeIndex < 0 || !$dd.is(':visible') || $dd.data('mention-editor') !== editor)) {
                /* Cancel before TinyMCE inserts a line break, keeping the query as text. */
                cancelMention(editor, state);
                return;
            }

            if (!$dd.length || !$dd.is(':visible') || $dd.data('mention-editor') !== editor || !state.results.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setActive(Math.min(state.activeIndex + 1, state.results.length - 1));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActive(Math.max(state.activeIndex - 1, 0));
            } else if (e.key === 'Enter' && state.activeIndex >= 0) {
                e.preventDefault();
                selectMention(state.results[state.activeIndex], editor, state);
            } else if (e.key === 'Tab' && state.results.length > 0) {
                e.preventDefault();
                selectMention(state.results[state.activeIndex >= 0 ? state.activeIndex : 0], editor, state);
            }
        }, true); /* Handle mention selection before TinyMCE handles Enter. */

        /*
         * SELECTIONCHANGE — cancel if cursor moves out of @query context.
         * Uses getQueryFromCursor(), not span.contains(), so it works even
         * if TinyMCE removed the span.
         */
        editor.on('SelectionChange', function () {
            if (!state.active || state.ignoreSelChange) return;
            if (state.span && state.span.parentNode) {
                /* Span alive — cancel only if cursor moved outside it.
                 * Do NOT use getQueryFromCursor here: it rejects spaces,
                 * which would wrongly cancel mentions for names with spaces. */
                var win = editor.getWin();
                var sel = win.getSelection();
                if (!sel || !sel.rangeCount) { cancelMention(editor, state); return; }
                var node = sel.getRangeAt(0).startContainer;
                if (!state.span.contains(node) && node !== state.span) {
                    cancelMention(editor, state);
                }
            } else {
                /* No span — fall back to text-position scan */
                var info = getQueryFromCursor(editor);
                if (!info) cancelMention(editor, state);
            }
        });

        editor.on('blur', function () {
            setTimeout(function () { cancelMention(editor, state); }, 200);
        });
    }

    /* ------------------------------------------------------------------
     * WAIT FOR TINYMCE — poll until the editor instance is ready
     * ------------------------------------------------------------------ */
    function waitForEditor(id) {
        var attempts = 0;
        var iv = setInterval(function () {
            if (typeof tinymce === 'undefined') { if (++attempts > 100) clearInterval(iv); return; }
            var ed = tinymce.get(id);
            if (ed && ed.initialized) { clearInterval(iv); setupForEditor(ed); return; }
            if (++attempts > 100) clearInterval(iv);
        }, 100);
    }

    /* ------------------------------------------------------------------
     * CLICK OUTSIDE — close dropdown
     * ------------------------------------------------------------------ */
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#mention-dropdown').length) {
            var insideEditor = false;
            if (activeEditorInstance) {
                var iframeEl = activeEditorInstance.iframeElement ||
                               (activeEditorInstance.getContainer &&
                                activeEditorInstance.getContainer().querySelector('iframe'));
                if (iframeEl && iframeEl === e.target) insideEditor = true;
            }
            if (!insideEditor && activeState) cancelMention(activeEditorInstance, activeState);
        }
    });

    /* ------------------------------------------------------------------
     * INIT
     * ------------------------------------------------------------------ */
    $(document).ready(function () {
        tinyMceId.split(',').forEach(function (id) {
            id = id.trim();
            if (id) waitForEditor(id);
        });
    });

})();
