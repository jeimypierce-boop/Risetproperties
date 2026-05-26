from pathlib import Path

base = Path(__file__).resolve().parent
files = [
    ('admin-all-enquiry.html', 'enquiry', 'admin-view-enquiry.html'),
    ('admin-Property-enquiry.html', 'enquiry', 'admin-view-enquiry.html'),
    ('admin-admission-enquiry.html', 'enquiry', 'admin-view-enquiry.html'),
    ('admin-common-enquiry.html', 'enquiry', 'admin-view-enquiry.html'),
    ('admin-event-enquiry.html', 'enquiry', 'admin-view-enquiry.html'),
    ('admin-seminar-enquiry.html', 'enquiry', 'admin-view-enquiry.html'),
    ('admin-event-all.html', 'viewing', 'admin-event-edit.html'),
    ('admin-seminar-all.html', 'maintenance', 'admin-seminar-edit.html'),
]

log = []
for filename, dtype, action_href in files:
    path = base / filename
    try:
        lines = path.read_text(encoding='utf-8').splitlines(True)
        new_lines = []
        delete_count = 0
        view_count = 0
        for line in lines:
            stripped = line.strip()
            if dtype == 'enquiry' and stripped == '<td><a href="admin-view-enquiry.html" class="ad-st-view">View</a></td>':
                view_count += 1
                new_lines.append(line)
                indent = line[:len(line) - len(line.lstrip())]
                new_lines.append(f"{indent}<td><a href=\"#\" class=\"ad-st-del btn-delete\" data-type=\"enquiry\" data-id=\"{view_count}\" style=\"color:#d9534f; margin-left:8px;\">Delete</a></td>\n")
                delete_count += 1
                continue
            if dtype == 'viewing' and stripped == '<td><a href="admin-event-edit.html" class="ad-st-view">Edit</a></td>':
                view_count += 1
                new_lines.append(line)
                indent = line[:len(line) - len(line.lstrip())]
                new_lines.append(f"{indent}<a href=\"#\" class=\"ad-st-del btn-delete\" data-type=\"viewing\" data-id=\"{view_count}\" style=\"color:#d9534f; margin-left:8px;\">Delete</a>\n")
                delete_count += 1
                continue
            if dtype == 'maintenance' and stripped == '<td><a href="admin-seminar-edit.html" class="ad-st-view">Edit</a></td>':
                view_count += 1
                new_lines.append(line)
                indent = line[:len(line) - len(line.lstrip())]
                new_lines.append(f"{indent}<a href=\"#\" class=\"ad-st-del btn-delete\" data-type=\"maintenance\" data-id=\"{view_count}\" style=\"color:#d9534f; margin-left:8px;\">Delete</a>\n")
                delete_count += 1
                continue
            if 'script src="js/custom.js"' in line and 'admin-delete.js' not in ''.join(lines):
                new_lines.append(line)
                new_lines.append('    <script src="js/admin-delete.js"></script>\n')
                continue
            if '<th>View</th>' in stripped and '<th>Delete</th>' not in ''.join(lines):
                new_lines.append(line.replace('<th>View</th>', '<th>View</th><th>Delete</th>'))
                continue
            if '<th>Edit</th>' in stripped and '<th>Actions</th>' not in ''.join(lines) and dtype in ('viewing', 'maintenance'):
                new_lines.append(line.replace('<th>Edit</th>', '<th>Actions</th>'))
                continue
            new_lines.append(line)
        path.write_text(''.join(new_lines), encoding='utf-8')
        log.append(f'Updated {filename}: added {delete_count} delete buttons')
    except Exception as e:
        log.append(f'Failed {filename}: {e}')
Path('fix_delete_log.txt').write_text('\n'.join(log), encoding='utf-8')
