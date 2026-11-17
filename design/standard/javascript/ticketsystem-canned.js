/*
 * ticketsystem: canned replies in the reply form (doc/canned-replies.md).
 * Copyright (C) 2026 7x. GNU General Public License v2.0 (or any later version).
 *
 * Without this script the form works as it is: choose a canned reply, press "Insert the text", the server puts the
 * filled-in text into the reply box. With it, the button inserts the text at the cursor (or over the selection) right
 * away, chooses the macro's status in the status list and leaves the choice in the select, so the reply applies the
 * rest of the macro when it is sent.
 *
 * Markup: an element [data-ts-canned] with data-ts-canned-target (the id of the textarea) and data-ts-canned-status
 * (the id of the status select, optional), holding a select whose options carry data-ts-text and data-ts-status, and a
 * button [data-ts-canned-insert].
 */
(function () {
    'use strict';

    function insertAtCursor(area, text) {
        var start = typeof area.selectionStart === 'number' ? area.selectionStart : area.value.length;
        var end = typeof area.selectionEnd === 'number' ? area.selectionEnd : area.value.length;
        var before = area.value.slice(0, start);
        var after = area.value.slice(end);
        // a blank line between the text and what is around it, as the server does when it appends
        if (before !== '' && !/\n\n$/.test(before)) {
            text = (/\n$/.test(before) ? '\n' : '\n\n') + text;
        }
        if (after !== '' && !/^\n/.test(after)) {
            text = text + '\n\n';
        }
        if (typeof area.setRangeText === 'function') {
            area.setRangeText(text, start, end, 'end');
        } else {
            area.value = before + text + after;
        }
        area.dispatchEvent(new Event('input', { bubbles: true }));
        area.focus();
    }

    function bind(box) {
        var select = box.querySelector('select');
        var button = box.querySelector('[data-ts-canned-insert]');
        var area = document.getElementById(box.getAttribute('data-ts-canned-target') || '');
        var status = document.getElementById(box.getAttribute('data-ts-canned-status') || '');
        if (!select || !button || !area) {
            return;
        }
        button.addEventListener('click', function (event) {
            var option = select.options[select.selectedIndex];
            if (!option || !option.hasAttribute('data-ts-text')) {
                return; // nothing chosen: let the form go to the server as without the script
            }
            event.preventDefault();
            insertAtCursor(area, option.getAttribute('data-ts-text'));
            var wanted = option.getAttribute('data-ts-status') || '';
            if (status && wanted !== '') {
                for (var i = 0; i < status.options.length; i++) {
                    if (status.options[i].value === wanted) {
                        status.selectedIndex = i;
                        break;
                    }
                }
            }
        });
    }

    function init() {
        var boxes = document.querySelectorAll('[data-ts-canned]');
        for (var i = 0; i < boxes.length; i++) {
            bind(boxes[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
