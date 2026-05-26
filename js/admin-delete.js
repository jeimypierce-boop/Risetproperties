// Shared admin delete handler
document.addEventListener('DOMContentLoaded', function() {
    document.body.addEventListener('click', function(e) {
        var el = e.target.closest('.btn-delete');
        if (!el) return;

        e.preventDefault();
        var type = el.getAttribute('data-type');
        var id = el.getAttribute('data-id');
        if (!type || !id) {
            alert('Missing delete information');
            return;
        }
        if (!confirm('Delete this item permanently? This action cannot be undone.')) return;
        var tr = el.closest('tr');
        fetch('delete.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id)
        }).then(function(res){ return res.json(); }).then(function(json){
            if (json.success) {
                if (tr) tr.parentNode.removeChild(tr);
                else location.reload();
            } else {
                alert('Delete failed: ' + json.message);
            }
        }).catch(function(){ alert('Request failed'); });
    });
});
