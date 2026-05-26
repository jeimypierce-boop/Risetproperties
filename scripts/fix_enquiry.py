from pathlib import Path
import re

path = Path('admin-all-enquiry.html')
text = path.read_text(encoding='utf-8')
print('before', text.count('<td><a href="admin-view-enquiry.html" class="ad-st-view">View</a></td>'))
count = 0

def repl(match):
    global count
    count += 1
    return match.group(1) + f"\n                                                    <td><a href='#' class='ad-st-del btn-delete' data-type='enquiry' data-id='{count}' style='color:#d9534f; margin-left:8px;'>Delete</a></td>"

text, n = re.subn(r'(<td><a href="admin-view-enquiry.html" class="ad-st-view">View</a></td>)', repl, text)
print('matches', n)
path.write_text(text, encoding='utf-8')
print('after', text.count('data-type=\'enquiry\''))
