(function () {
    'use strict';

    function escapeHtml(text) {
        return String(text || '').replace(/[&<>\"']/g, function (chr) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[chr];
        });
    }

    function updateTopMenu(data) {
        var topUser = document.querySelector('.top-user-pro');
        if (topUser) {
            var icon = topUser.querySelector('i');
            var img = topUser.querySelector('img');
            var name = data.name || 'My Account';
            if (img) {
                topUser.innerHTML = '';
                topUser.appendChild(img);
                topUser.appendChild(document.createTextNode(' ' + name + ' '));
                if (icon) {
                    topUser.appendChild(icon);
                }
            }
        }

        var menu = document.getElementById('top-menu');
        if (menu) {
            var userInfo = menu.querySelector('.user-info');
            if (!userInfo) {
                userInfo = document.createElement('li');
                userInfo.className = 'user-info';
                menu.insertBefore(userInfo, menu.firstChild);
            }
            var roleText = data.role ? escapeHtml(data.role) : '';
            var emailText = data.email ? ' · ' + escapeHtml(data.email) : '';
            userInfo.innerHTML = '<strong>' + escapeHtml(data.name || 'Account') + '</strong><br><small>' + roleText + emailText + '</small>';

            menu.querySelectorAll('a').forEach(function (anchor) {
                if (/logout/i.test(anchor.textContent) && anchor.getAttribute('href') === '#') {
                    anchor.setAttribute('href', 'logout.php');
                }
                if (/admin setting/i.test(anchor.textContent) && anchor.getAttribute('href') === 'admin-panel-setting.html') {
                    anchor.setAttribute('href', 'admin-panel-setting.html');
                }
            });
        }
    }

    function updateSidebarInfo(data) {
        var header = document.querySelector('.sb2-12 ul li:nth-child(2) h5');
        if (!header) {
            return;
        }

        var currentText = header.textContent || '';
        if (!/Victoria Baker|Santa Ana|My Account/i.test(currentText)) {
            return;
        }

        var span = header.querySelector('span');
        if (span) {
            span.textContent = data.role || '';
        }
        header.childNodes.forEach(function (node) {
            if (node.nodeType === Node.TEXT_NODE) {
                node.textContent = data.name ? data.name + ' ' : 'My Account ';
            }
        });

        var infoBlock = header.parentElement;
        if (infoBlock) {
            var emailParagraph = infoBlock.querySelector('p');
            if (!emailParagraph) {
                emailParagraph = document.createElement('p');
                infoBlock.appendChild(emailParagraph);
            }
            emailParagraph.textContent = data.email || '';
        }
    }

    function init() {
        fetch('user_session_info.php', { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function (data) {
                if (data && data.success) {
                    updateTopMenu(data);
                    updateSidebarInfo(data);
                }
            })
            .catch(function () {
                // Ignore failures; page will continue using placeholders.
            });
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }
}());
