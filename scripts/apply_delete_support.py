from pathlib import Path
import re

base = Path(__file__).resolve().parent
pages = [
    ('admin-all-enquiry.html', 'enquiry'),
    ('admin-Property-enquiry.html', 'enquiry'),
    ('admin-admission-enquiry.html', 'enquiry'),
    ('admin-common-enquiry.html', 'enquiry'),
    ('admin-event-enquiry.html', 'enquiry'),
    ('admin-seminar-enquiry.html', 'enquiry'),
    ('admin-event-all.html', 'viewing'),
    ('admin-seminar-all.html', 'maintenance'),
]

for filename, dtype in pages:
    path = base / filename
    text = path.read_text(encoding='utf-8')
    modified = False

    if 'js/admin-delete.js' not in text and 'js/custom.js' in text:
        text = text.replace('<script src="js/custom.js"></script>', '<script src="js/custom.js"></script>\n    <script src="js/admin-delete.js"></script>')
        modified = True

    if dtype == 'enquiry':
        if '<th>View</th>' in text and '<th>Delete</th>' not in text:
            text = text.replace('<th>View</th>', '<th>View</th><th>Delete</th>', 1)
            modified = True

        count = 0
        def enquiry_repl(match):
            nonlocal count, modified
            count += 1
            modified = True
            return match.group(0) + f'\n                                                    <td><a href="#" class="ad-st-del btn-delete" data-type="enquiry" data-id="{count}" style="color:#d9534f; margin-left:8px;">Delete</a></td>'

        text, replacements = re.subn(r'(<td><a href="admin-view-enquiry\.html" class="ad-st-view">View</a></td>)', enquiry_repl, text)
        if replacements > 0:
            modified = True

    elif dtype == 'viewing':
        if '<th>Edit</th>' in text and '<th>Actions</th>' not in text:
            text = text.replace('<th>Edit</th>', '<th>Actions</th>', 1)
            modified = True

        count = 0
        def viewing_repl(match):
            nonlocal count, modified
            count += 1
            modified = True
            return match.group(0) + f' <a href="#" class="ad-st-del btn-delete" data-type="viewing" data-id="{count}" style="color:#d9534f; margin-left:8px;">Delete</a>'

        text, replacements = re.subn(r'(<td><a href="admin-event-edit\.html" class="ad-st-view">Edit</a></td>)', viewing_repl, text)
        if replacements > 0:
            modified = True

    elif dtype == 'maintenance':
        if '<th>Edit</th>' in text and '<th>Actions</th>' not in text:
            text = text.replace('<th>Edit</th>', '<th>Actions</th>', 1)
            modified = True

        count = 0
        def maintenance_repl(match):
            nonlocal count, modified
            count += 1
            modified = True
            return match.group(0) + f' <a href="#" class="ad-st-del btn-delete" data-type="maintenance" data-id="{count}" style="color:#d9534f; margin-left:8px;">Delete</a>'

        text, replacements = re.subn(r'(<td><a href="admin-seminar-edit\.html" class="ad-st-view">Edit</a></td>)', maintenance_repl, text)
        if replacements > 0:
            modified = True

    if modified:
        path.write_text(text, encoding='utf-8')
        print(f'Updated {filename}')
print('Done')
