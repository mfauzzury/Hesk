/**
 * mention-autocomplete.js
 *
 * @mention autocomplete for HESK admin panel — plain text / contenteditable mode.
 *
 * Uses the span-wrapping approach:
 *   1. When @ is typed, the character is replaced with a live
 *      <span class="mention-in-progress">@…</span> that tracks the query.
 *   2. The dropdown is driven by the span's text content — no fragile anchor
 *      offsets, no node-split edge cases, no &nbsp; surprises.
 *   3. On selection the span is swapped for a .mention-chip with a stable staff ID.
 *   4. On cancel/Escape the span is unwrapped back to plain text.
 *
 * Requires window.HeskMention before this script loads:
 *   window.HeskMention = { ticketId, editorSelector, searchingText }
 */

(function () {
    var cfg            = window.HeskMention || {};
    var ticketId       = cfg.ticketId       || 0;
    var category       = cfg.category       || 0;
    var editorSelector = cfg.editorSelector || '';
    var searchingText  = cfg.searchingText;

    if (!editorSelector) return;

    var activeEditor         = null;
    var activeInProgressSpan = null;  /* the live <span class="mention-in-progress"> */
    var activeIndex          = -1;
    var mentionResults       = [];
    var mentionXhr           = null;
    var suppressMention      = false;
    var editingMention       = false;
    var mentionSequence      = 0;

    /* ------------------------------------------------------------------
     * SETUP: hide each target textarea, insert a contenteditable div
     * ------------------------------------------------------------------ */
    function setupEditors() {
        $(editorSelector).each(function () {
            var $ta     = $(this);
            var $editor = $('<div>', {
                'class':           'hesk-mention-editor',
                'contenteditable': 'true',
                'spellcheck':      'true',
                'role':            'textbox',
                'aria-multiline':  'true',
                'aria-label':      $ta.attr('aria-label') || ''
            });
            if ($ta.attr('placeholder')) $editor.attr('data-placeholder', $ta.attr('placeholder'));
            if ($ta.val()) $editor.html(getEditorContent($ta.val()));
            $ta.hide().after($editor);
            $editor.data('textarea', $ta[0]);
            $ta.data('editor', $editor[0]);
            /* Existing Hesk links and validation focus the original field. */
            $ta[0].focus = function () { $editor[0].focus(); };
            if ($ta.attr('id')) {
                $editor.attr('id', $ta.attr('id') + '_mention');
                $('label').filter(function () { return this.htmlFor === $ta.attr('id'); })
                    .on('click', function (e) { e.preventDefault(); $editor[0].focus(); });
            }
        });
    }

    /* Restore selected mentions while keeping ordinary markup as literal text. */
    function getEditorContent(value) {
        var content = document.createElement('div');
        if (!value) return '';
        value.split('\n').forEach(function (line) {
            var block = document.createElement('div');
            var pattern = /<span\b[^>]*\sid="(hesk-mention-[0-9]+(?:-[a-z0-9]+)*)"[^>]*>([^<]*)<\/span>/gi;
            var offset = 0;
            var match;
            while ((match = pattern.exec(line))) {
                block.appendChild(document.createTextNode(line.slice(offset, match.index)));
                var decoded = document.createElement('textarea');
                decoded.innerHTML = match[2];
                var chip = document.createElement('span');
                chip.className = 'mention-chip';
                chip.contentEditable = 'false';
                chip.id = match[1];
                chip.textContent = decoded.value;
                var title = match[0].match(/\btitle="([^"]*)"/i);
                if (title) {
                    decoded.innerHTML = title[1];
                    chip.title = decoded.value;
                }
                block.appendChild(chip);
                offset = pattern.lastIndex;
            }
            block.appendChild(document.createTextNode(line.slice(offset)));
            if (!line) block.appendChild(document.createElement('br'));
            content.appendChild(block);
        });
        return content.innerHTML;
    }

    /* ------------------------------------------------------------------
     * SYNC: write contenteditable content back to the hidden textarea.
     * Selected mentions retain the staff ID independently of their display name.
     * .mention-in-progress → plain text (unresolved mention kept as typed)
     * ------------------------------------------------------------------ */
    function getPlainText(editor) {
        var lines = [];
        var text = '';
        var hasText = false;
        var trailingBr = false;

        function flushText() {
            if (!hasText) return;
            /* A final BR only keeps an empty line editable; it is not another newline. */
            lines.push(trailingBr ? text.slice(0, -1) : text);
            text = '';
            hasText = trailingBr = false;
        }

        function readNode(node) {
            if (node.nodeType === 3 && node.nodeValue) {
                text += node.nodeValue;
                hasText = true;
                trailingBr = false;
            } else if (node.nodeType === 1 && node.classList.contains('mention-chip') && /^hesk-mention-[0-9]+(?:-[a-z0-9]+)*$/i.test(node.id)) {
                var mention = document.createElement('span');
                mention.id = node.id;
                mention.textContent = node.textContent;
                text += mention.outerHTML;
                hasText = true;
                trailingBr = false;
            } else if (node.nodeName === 'BR') {
                text += '\n';
                hasText = trailingBr = true;
            } else if (node.nodeName === 'DIV' || node.nodeName === 'P') {
                /* Editing can leave empty wrappers that do not represent a line. */
                if (node.textContent || node.querySelector('br')) {
                    flushText();
                    lines.push(getPlainText(node));
                }
            } else {
                for (var child = node.firstChild; child; child = child.nextSibling) readNode(child);
            }
        }

        for (var child = editor.firstChild; child; child = child.nextSibling) readNode(child);
        flushText();
        return lines.join('\n');
    }

    function syncToTextarea(editor) {
        var ta = $(editor).data('textarea');
        if (!ta) return;
        /* Read logical lines, not innerText's extra breaks around empty blocks. */
        ta.value = getPlainText(editor).replace(/\u00a0/g, ' ');
        $(ta).trigger('input');
    }

    /* Canned responses still write to the original textarea. */
    window.heskSyncMentionEditor = function (ta) {
        var editor = $(ta).data('editor');
        if (!editor) return;
        if (editor === activeEditor) cancelMention();
        var range = document.createRange();
        range.selectNodeContents(editor);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        editingMention = true;
        document.execCommand('insertHTML', false, getEditorContent(ta.value));
        editingMention = false;
        syncToTextarea(editor);
    };

    /* ------------------------------------------------------------------
     * CARET RECT: read the cursor position without changing the undo history.
     * ------------------------------------------------------------------ */
    function getCaretRect() {
        var sel = window.getSelection();
        if (!sel || !sel.rangeCount) return null;
        var range = sel.getRangeAt(0).cloneRange();
        range.collapse(true);
        var rect = range.getBoundingClientRect();
        return rect.height ? rect : activeEditor.getBoundingClientRect();
    }

    /* ------------------------------------------------------------------
     * DROPDOWN helpers
     * ------------------------------------------------------------------ */
    function getDropdown() {
        var $dd = $('#mention-dropdown');
        if (!$dd.length) $dd = $('<ul id="mention-dropdown" role="listbox"></ul>').appendTo('body');
        return $dd;
    }

    function positionDropdown() {
        var rect = getCaretRect();
        if (!rect) return;
        getDropdown().css({
            top:  rect.bottom + window.scrollY + 4,
            left: rect.left   + window.scrollX
        });
    }

    function setActive(idx) {
        var $items = getDropdown().children('li');
        $items.removeClass('mention-active');
        if (idx >= 0 && idx < $items.length) $items.eq(idx).addClass('mention-active');
        activeIndex = idx;
    }

    function renderItem(user, i) {
        var $li = $('<li role="option"></li>');
        $li.html(
            '<span class="mention-user">@' + $('<b>').text(user.name).html() + '</span>' +
            '<span class="mention-name">' + $('<b>').text(user.email + ' (#' + user.id + ')').html() + '</span>'
        );
        $li.on('mousedown', function (e) { e.preventDefault(); selectMention(mentionResults[i]); });
        return $li;
    }

    function showResults(results) {
        var $dd = getDropdown();
        $dd.empty();
        mentionResults = results;
        activeIndex    = -1;
        if (!results.length) { $dd.hide(); return; }
        results.forEach(function (user, i) { $dd.append(renderItem(user, i)); });
        positionDropdown();
        $dd.show();
    }

    function doSearch(query) {
        if (mentionXhr) mentionXhr.abort();
        mentionResults = [];
        activeIndex = -1;
        getDropdown()
            .data('mention-editor', activeEditor)
            .empty()
            .append($('<li class="mention-loading"><span class="mention-spinner"></span></li>').append(document.createTextNode(' ' + searchingText)));
        positionDropdown();
        getDropdown().show();
        mentionXhr = $.ajax({
            url:      'ajax/search_staff.php',
            type:     'GET',
            data:     { query: query, ticket_id: ticketId, category: category },
            dataType: 'json',
            success:  function (data) { mentionXhr = null; if (activeInProgressSpan && getDropdown().data('mention-editor') === activeEditor) showResults(Array.isArray(data) ? data : []); },
            error:    function (xhr, status) { if (status !== 'abort') if ($('#mention-dropdown').data('mention-editor') === activeEditor) $('#mention-dropdown').hide().empty(); }
        });
    }

    /* ------------------------------------------------------------------
     * CANCEL MENTION: unwrap the in-progress span back to plain text
     * ------------------------------------------------------------------ */
    function cancelMention() {
        if (!activeInProgressSpan) return;
        var span = activeInProgressSpan;
        activeInProgressSpan = null;
        activeIndex = -1;
        mentionResults = [];
        if (mentionXhr) { mentionXhr.abort(); mentionXhr = null; }
        if ($('#mention-dropdown').data('mention-editor') === activeEditor) $('#mention-dropdown').hide().empty();
        if (!span.parentNode) return;

        var sel = window.getSelection();
        var focusedElement = document.activeElement;
        var originalRange = sel && sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
        var inside = originalRange && span.contains(originalRange.startContainer) && span.contains(originalRange.endContainer);
        var startOffset = 0;
        var endOffset = 0;
        if (inside) {
            var before = originalRange.cloneRange();
            before.selectNodeContents(span);
            before.setEnd(originalRange.startContainer, originalRange.startOffset);
            startOffset = before.toString().length;
            endOffset = startOffset + originalRange.toString().length;
        }
        var text = span.textContent;
        var range = document.createRange();
        range.selectNode(span);
        sel.removeAllRanges();
        sel.addRange(range);
        editingMention = true;
        document.execCommand('insertText', false, text);
        editingMention = false;
        if (inside && sel.rangeCount) {
            range = sel.getRangeAt(0).cloneRange();
            var offset = range.endOffset - text.length;
            if (range.endContainer.nodeType === 3 && offset >= 0) {
                range.setStart(range.endContainer, offset + startOffset);
                range.setEnd(range.endContainer, offset + endOffset);
                sel.removeAllRanges();
                sel.addRange(range);
            }
        } else if (originalRange) {
            sel.removeAllRanges();
            sel.addRange(originalRange);
        }
        syncToTextarea(activeEditor);
        if (focusedElement && document.activeElement !== focusedElement) focusedElement.focus();
    }

    /* ------------------------------------------------------------------
     * START MENTION: replace the just-typed @ with an in-progress span.
     * The span becomes the single source of truth for the query text.
     * ------------------------------------------------------------------ */
    function startMention(editor) {
        var sel = window.getSelection();
        if (!sel || !sel.rangeCount) return;
        var range = sel.getRangeAt(0);

        range.setStart(range.startContainer, range.startOffset - 1);
        editingMention = true;
        document.execCommand('insertHTML', false, '<span id="hesk-mention-query-' + (++mentionSequence) + '" class="mention-in-progress">@</span>');
        editingMention = false;
        var span = editor.querySelector('#hesk-mention-query-' + mentionSequence);
        if (!span) {
            /* Chromium can retain only computed styles when inserting into an existing block. */
            var node = sel.anchorNode;
            var parent = node && node.nodeType === 3 ? node.parentNode : null;
            if (parent && parent.nodeName === 'SPAN' && parent.textContent === '@' &&
                editor.contains(parent) && !/^hesk-mention-[0-9]+(?:-[a-z0-9]+)*$/i.test(parent.id)) {
                span = parent;
                span.id = 'hesk-mention-query-' + mentionSequence;
                span.className = 'mention-in-progress';
            }
        }
        if (!span) { sel.collapseToEnd(); return; }
        var newRange = document.createRange();
        newRange.selectNodeContents(span);
        newRange.collapse(false);
        sel.removeAllRanges();
        sel.addRange(newRange);
        activeInProgressSpan = span;
        activeEditor = editor;
        doSearch(''); /* show all eligible staff immediately */
    }

    /* ------------------------------------------------------------------
     * SELECT MENTION: replace in-progress span with a .mention-chip
     * ------------------------------------------------------------------ */
    function selectMention(user) {
        if (!user || !activeInProgressSpan) return;

        /* Build the chip */
        var chip = document.createElement('span');
        chip.className        = 'mention-chip';
        chip.contentEditable  = 'false';
        chip.id               = 'hesk-mention-' + user.id + '-' + Date.now().toString(36) + '-' + (++mentionSequence);
        chip.title            = user.name + (user.email ? ' <' + user.email + '>' : '') + ' (#' + user.id + ')';
        chip.textContent      = '@' + user.name;

        var range = document.createRange();
        range.selectNode(activeInProgressSpan);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        activeInProgressSpan = null;
        editingMention = true;
        document.execCommand('insertHTML', false, chip.outerHTML + '&nbsp;');
        editingMention = false;
        if (activeEditor) {
            syncToTextarea(activeEditor);
            activeEditor.focus();
        }

        activeIndex    = -1;
        mentionResults = [];
        if (mentionXhr) { mentionXhr.abort(); mentionXhr = null; }
        if ($('#mention-dropdown').data('mention-editor') === activeEditor) $('#mention-dropdown').hide().empty();
    }

    /* ------------------------------------------------------------------
     * INPUT HANDLER
     * ------------------------------------------------------------------ */
    function handleInput(editor, event) {
        if (editingMention) return;
        syncToTextarea(editor);
        if (cfg.canAssign === false) return;
        if (event && /^(historyUndo|historyRedo)$/.test(event.inputType)) {
            activeInProgressSpan = null;
            if (mentionXhr) { mentionXhr.abort(); mentionXhr = null; }
            mentionResults = [];
            if ($('#mention-dropdown').data('mention-editor') === activeEditor) $('#mention-dropdown').hide().empty();
            return;
        }
        if (suppressMention) { suppressMention = false; return; }

        if (activeInProgressSpan) {
            /* Already in a mention — verify cursor is still inside the span */
            var sel  = window.getSelection();
            if (!sel || !sel.rangeCount) { cancelMention(); return; }
            var node = sel.getRangeAt(0).startContainer;

            if (!activeInProgressSpan.contains(node) && node !== activeInProgressSpan) {
                cancelMention(); return;
            }
            if (!activeInProgressSpan.textContent) { cancelMention(); return; }

            /* Query = everything after the leading @ */
            var query = activeInProgressSpan.textContent.slice(1).replace(/\u00a0/g, ' ');
            doSearch(query);

        } else {
            /* Detect freshly typed @ */
            var sel2 = window.getSelection();
            if (!sel2 || !sel2.rangeCount) return;
            var r = sel2.getRangeAt(0);
            var n = r.startContainer;
            var o = r.startOffset;
            if (n.nodeType === 3 && o > 0 && n.textContent[o - 1] === '@') {
                /* Only trigger if @ is at the start or preceded by whitespace.
                 * Prevents false triggers on email addresses like foo@bar.com */
                var charBefore = o >= 2 ? n.textContent[o - 2] : '';
                if (charBefore === '' || /[\s\u00a0]/.test(charBefore)) {
                    startMention(editor);
                }
            }
        }
    }

    /* ------------------------------------------------------------------
     * KEYDOWN: arrow navigation, confirm, Backspace-cancel, Escape
     * ------------------------------------------------------------------ */
    function handleKeydown(e) {
        if (activeInProgressSpan) {
            if (e.key === 'Escape') {
                cancelMention();
                return;
            }
            if (e.key === 'Backspace') {
                /* Let the browser delete at the current caret or selection. */
                cancelMention();
                suppressMention = true;
                setTimeout(function () { suppressMention = false; }, 0);
                return;
            }
        }

        var $dd = $('#mention-dropdown');
        if (!$dd.length || !$dd.is(':visible') || !mentionResults.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive(Math.min(activeIndex + 1, mentionResults.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(Math.max(activeIndex - 1, 0));
        } else if (e.key === 'Enter' && activeIndex >= 0) {
            e.preventDefault();
            selectMention(mentionResults[activeIndex]);
        } else if (e.key === 'Tab' && mentionResults.length > 0) {
            e.preventDefault();
            selectMention(mentionResults[activeIndex >= 0 ? activeIndex : 0]);
        }
    }

    /* ------------------------------------------------------------------
     * SELECTIONCHANGE: cancel if cursor navigates outside the span
     * (e.g. user presses arrow keys or clicks elsewhere in the editor)
     * ------------------------------------------------------------------ */
    document.addEventListener('selectionchange', function () {
        if (!activeInProgressSpan) return;
        var sel = window.getSelection();
        if (!sel || !sel.rangeCount) { cancelMention(); return; }
        var node = sel.getRangeAt(0).startContainer;
        if (!activeInProgressSpan.contains(node) && node !== activeInProgressSpan) {
            cancelMention();
        }
    });

    /* ------------------------------------------------------------------
     * INIT
     * ------------------------------------------------------------------ */
    $(document).ready(function () {
        setupEditors();

        $(document).on('input',   '.hesk-mention-editor', function (e) { handleInput(this, e.originalEvent); })
                   .on('beforeinput', '.hesk-mention-editor', function (e) {
                       /* Shift+Enter must use the same line structure as Enter. */
                       if (e.originalEvent.inputType === 'insertLineBreak' && e.originalEvent.cancelable) {
                           e.preventDefault();
                           document.execCommand('insertParagraph', false, null);
                       }
                   })
                   .on('keydown', '.hesk-mention-editor', handleKeydown)
                   .on('paste', '.hesk-mention-editor', function (e) {
                       var clipboard = e.originalEvent.clipboardData;
                       if (!clipboard) return;
                       e.preventDefault();
                       document.execCommand('insertText', false, clipboard.getData('text/plain'));
                   })
                   .on('drop', '.hesk-mention-editor', function (e) {
                       var transfer = e.originalEvent.dataTransfer;
                       if (!transfer) return;
                       e.preventDefault();
                       this.focus();
                       var range = document.caretRangeFromPoint && document.caretRangeFromPoint(e.clientX, e.clientY);
                       if (range && this.contains(range.startContainer)) {
                           var sel = window.getSelection();
                           sel.removeAllRanges();
                           sel.addRange(range);
                       }
                       document.execCommand('insertText', false, transfer.getData('text/plain'));
                   })
                   .on('blur',    '.hesk-mention-editor', function () {
                       var editor = this;
                       setTimeout(function () { if (activeEditor === editor) cancelMention(); }, 200);
                   });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#mention-dropdown, .hesk-mention-editor').length) {
                cancelMention();
            }
        });

        $(document).on('submit', 'form', function () {
            $(this).find('.hesk-mention-editor').each(function () { syncToTextarea(this); });
        });
    });

})();
