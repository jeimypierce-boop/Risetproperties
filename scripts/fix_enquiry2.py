from pathlib import Path

path = Path('admin-all-enquiry.html')
text = path.read_text(encoding='utf-8')
old = '<td><a href="admin-view-enquiry.html" class="ad-st-view">View</a></td>'
new = '\1'
replacement = '...'
# Replace all view anchor occurrences with appended delete action using row counters.
count = 0
result = []
index = 0
while True:
    pos = text.find(old, index)
    if pos == -1:
        result.append(text[index:])
        break
    result.append(text[index:pos])
    count += 1
    result.append(old + f"\n                                                    <td><a href=\"#\" class=\"ad-st-del btn-delete\" data-type=\"enquiry\" data-id=\"{count}\" style=\"color:#d9534f; margin-left:8px;\">Delete</a></td>")
    index = pos + len(old)
new_text = ''.join(result)
path.write_text(new_text, encoding='utf-8')
print('replaced', count)
