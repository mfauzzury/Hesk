(function () {
    'use strict';

    function countMatches(text, regex) {
        var matches = text.match(regex);
        return matches ? matches.length : 0;
    }

    function getFirstExistingId(ids) {
        var i;

        if (!ids || !ids.length) {
            return '';
        }

        for (i = 0; i < ids.length; i++) {
            if (ids[i] && document.getElementById(ids[i])) {
                return ids[i];
            }
        }

        return '';
    }

    function checkPasswordRules(password, config) {
        var rules = [];
        var lowerCount = countMatches(password, /[a-z]/g);
        var upperCount = countMatches(password, /[A-Z]/g);
        var numberCount = countMatches(password, /[0-9]/g);
        var specialCount = countMatches(password, /[!@#$%^&*()\-_=+\[\]{}|\\:;"'<>,.?/]/g);
        var isCommonPassword = false;

        if (config.checkCommon && config.commonPasswords && config.commonPasswords.length) {
            isCommonPassword = config.commonPasswords.indexOf(password.toLowerCase()) !== -1;
        }

        if (config.minLength > 0) {
            rules.push({
                ok: password.replace(/[\uD800-\uDBFF][\uDC00-\uDFFF]/g, '_').length >= config.minLength,
                message: config.msg.minLength
            });
        }

        if (config.lowercase > 0) {
            rules.push({
                ok: lowerCount >= config.lowercase,
                message: config.msg.lowercase
            });
        }

        if (config.uppercase > 0) {
            rules.push({
                ok: upperCount >= config.uppercase,
                message: config.msg.uppercase
            });
        }

        if (config.numbers > 0) {
            rules.push({
                ok: numberCount >= config.numbers,
                message: config.msg.numbers
            });
        }

        if (config.special > 0) {
            rules.push({
                ok: specialCount >= config.special,
                message: config.msg.special
            });
        }

        if (config.checkCommon && config.commonPasswords && config.commonPasswords.length) {
            rules.push({
                ok: !isCommonPassword,
                message: config.msg.common
            });
        }

        // Password history cannot be checked in JavaScript because it depends on server-side stored hashes.
        if (config.historyLimit > 0) {
            rules.push({
                ok: null,
                message: config.msg.historyLimit
            });
        }

        return rules;
    }

    function showPasswordRules(feedback, rules) {
        var html = '';
        var i;

        if (!feedback) {
            return;
        }

        if (!rules.length) {
            feedback.innerHTML = '';
            feedback.style.display = 'none';
            return;
        }

        html += '<ul class="hesk-pass-list">';

        for (i = 0; i < rules.length; i++) {
            if (rules[i].ok === true) {
                html += '<li class="hesk-pass-ok">&#10003; ' + rules[i].message + '</li>';
            } else if (rules[i].ok === false) {
                html += '<li class="hesk-pass-fail">&#10007; ' + rules[i].message + '</li>';
            } else {
                html += '<li class="hesk-pass-note">Note: ' + rules[i].message + '</li>';
            }
        }

        html += '</ul>';

        feedback.innerHTML = html;
        feedback.style.display = 'block';
    }

    function updatePasswordRules(input, feedback, config) {
        var password;

        if (!input || !feedback || !config) {
            return;
        }

        password = input.value;

        if (!password.length) {
            feedback.innerHTML = '';
            feedback.style.display = 'none';
            return;
        }

        showPasswordRules(feedback, checkPasswordRules(password, config));
    }

    function updatePasswordConfirmation(passwordInput, confirmInput, feedback, matchText, noMatchText) {
        var password;
        var confirmPassword;
        var isMatch;

        if (!passwordInput || !confirmInput || !feedback) {
            return;
        }

        password = passwordInput.value;
        confirmPassword = confirmInput.value;

        if (!confirmPassword.length) {
            feedback.innerHTML = '';
            feedback.style.display = 'none';
            return;
        }

        isMatch = password === confirmPassword;

        feedback.innerHTML =
            '<p class="hesk-pass-confirm ' + (isMatch ? 'hesk-pass-ok' : 'hesk-pass-fail') + '">' +
            (isMatch ? '&#10003; ' + matchText : '&#10007; ' + noMatchText) +
            '</p>';

        feedback.style.display = 'block';
    }

    function initHeskPasswordComplexity(inputId, feedbackId, config) {
        var input = document.getElementById(inputId);
        var feedback = document.getElementById(feedbackId);
        var initKey;

        if (!input || !feedback || !config) {
            return;
        }

        initKey = 'data-hesk-pass-init-' + feedbackId;
        if (input.getAttribute(initKey) === '1') {
            updatePasswordRules(input, feedback, config);
            return;
        }

        input.setAttribute(initKey, '1');

        input.addEventListener('input', function () {
            updatePasswordRules(input, feedback, config);
        });

        updatePasswordRules(input, feedback, config);
    }

    function initHeskConfirmPassword(passwordId, confirmId, feedbackId, matchText, noMatchText) {
        var passwordInput = document.getElementById(passwordId);
        var confirmInput = document.getElementById(confirmId);
        var feedback = document.getElementById(feedbackId);
        var initKey;

        if (!passwordInput || !confirmInput || !feedback) {
            return;
        }

        matchText = matchText || 'Passwords match';
        noMatchText = noMatchText || 'Passwords do not match';

        initKey = 'data-hesk-confirm-init-' + feedbackId;
        if (confirmInput.getAttribute(initKey) === '1') {
            updatePasswordConfirmation(passwordInput, confirmInput, feedback, matchText, noMatchText);
            return;
        }

        confirmInput.setAttribute(initKey, '1');

        function refreshConfirmation() {
            updatePasswordConfirmation(passwordInput, confirmInput, feedback, matchText, noMatchText);
        }

        passwordInput.addEventListener('input', refreshConfirmation);
        confirmInput.addEventListener('input', refreshConfirmation);

        refreshConfirmation();
    }

    window.initHeskPasswordComplexity = initHeskPasswordComplexity;
    window.initHeskConfirmPassword = initHeskConfirmPassword;

    document.addEventListener('DOMContentLoaded', function () {
        var config = window.heskPassConfig || {};
        var messages = config.msg || {};
        var inputId;
        var containerId;
        var confirmId;
        var confirmContainerId;

        inputId = getFirstExistingId([
            config.inputId,
            'prof_newpass',
            'pass_new'
        ]);

        containerId = getFirstExistingId([
            config.containerId,
            'hesk-pass-requirements',
            'hesk-pass-requirements-2'
        ]);

        confirmId = getFirstExistingId([
            config.confirmId,
            'prof_newpass2',
            'pass_new2'
        ]);

        confirmContainerId = getFirstExistingId([
            config.confirmContainerId,
            'hesk-confirm-pass-staff',
            'hesk-confirm-pass-admin-profile'
        ]);

        if (inputId && containerId) {
            initHeskPasswordComplexity(inputId, containerId, config);
        }

        if (inputId && confirmId && confirmContainerId) {
            initHeskConfirmPassword(
                inputId,
                confirmId,
                confirmContainerId,
                messages.passMatch || 'Passwords match',
                messages.passNoMatch || 'Confirm Password does not match the Password.'
            );
        }
    });
})();