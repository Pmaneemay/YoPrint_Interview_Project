
window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: window.PUSHER_KEY,
    cluster: window.PUSHER_CLUSTER, 
    forceTLS: true, 
});

$(document).ready(function() {
    // Initialize datatable
    const table = $('#files-table').DataTable({
        "paging": false,
        "info": false,
        "searching": false,
        "order": [[0, "desc"]],
        "columns": [
            { "width": "30%" },
            { "width": "45%" },
            { "width": "25%" }
        ]
    });

    $.get('/uploads', function(files) {
        files.forEach(function(file) {
            console.log('From /uploads endpoint:', file.updated_at || file.created_at);
            const updatedDate = new Date(file.updated_at || file.created_at);
            const formatted = `${updatedDate.toLocaleString()}<br><span class="time-diff" style="color:#888;font-size:0.92em">${getTimeAgo(updatedDate)}</span>`;
            const rowNode = table.row.add([
                formatted,
                file.display_name,
                `<span class="status">${file.status}</span>`
            ]).draw(false).node();
            $(rowNode).attr('data-file-id', file.id);
            $(rowNode).attr('data-updated-at', updatedDate.toISOString());
        });
    });

    // Time-ago helper
    function getTimeAgo(date) {
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        if (seconds < 60) return "just now";
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes} minute${minutes !== 1 ? "s" : ""} ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours} hour${hours !== 1 ? "s" : ""} ago`;
        const days = Math.floor(hours / 24);
        return `${days} day${days !== 1 ? "s" : ""} ago`;
    }

    // Update all time-diff spans every minute
    setInterval(function() {
        $('#files-table tbody tr').each(function() {
            const row = $(this);
            const updatedAt = row.attr('data-updated-at');
            if (updatedAt) {
                const updatedDate = new Date(updatedAt.replace(' ', 'T'));
                row.find('.time-diff').text(getTimeAgo(updatedDate));
            }
        });
    }, 60000);

    // Handle file select button
    $('#UploadBtn').on('click', function() {
        $('#file-input').click();
    });

    // File input change (Select File)
    $('#file-input').on('change', function(e) {
        handleFile(e.target.files[0]);
    });

    // Drag and drop handlers
    const $dropArea = $('#file-container');
    $dropArea.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $dropArea.addClass('dragover');
    }).on('dragleave drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $dropArea.removeClass('dragover');
    }).on('drop', function(e) {
        if (e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files.length) {
            handleFile(e.originalEvent.dataTransfer.files[0]);
        }
    });

 function handleFile(file) {
    if (!file) return;
    $.ajax({
        url: '/upload/init',
        type: 'POST',
        data: { file_name: file.name, file_size: file.size },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(res) {
            const fileId = res.id;
            const now = new Date();
            const timeStr = now.toLocaleString();
            const diffStr = getTimeAgo(now);

            // Show in table immediately with returned ID
            const rowNode = table.row.add([
                `${timeStr}<br><span class="time-diff" style="color:#888;font-size:0.92em">${diffStr}</span>`,
                file.name,
                `<span class="status">uploading</span>`
            ]).draw(false).node();
            $(rowNode).attr('data-file-id', fileId);
            $(rowNode).attr('data-updated-at', now.toISOString());

            // Send file to /upload/{id}/finish
            const formData = new FormData();
            formData.append('file', file);

            $.ajax({
                url: `/upload/${fileId}/finish`,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $(rowNode).find('.status').text(response.status);
                },
                error: function() {
                    $(rowNode).find('.status').text('failed');
                }
            });
        },
        error: function() {
            alert('Failed to initialize file upload');
        }
    });
}


window.Echo.channel('file-uploads')
    .listen('FileStatusUpdated', function(e) {
        // Find row by file ID
        const row = $(`tr[data-file-id="${e.id}"]`);

        if (row.length) {
            // Update status
            row.find('.status').text(e.status);
            // Update time column
            const updatedDate = new Date(e.time);
            const diff = getTimeAgo(updatedDate);
            const formatted = `${updatedDate.toLocaleString()}<br><span class="time-diff" style="color:#888;font-size:0.92em">${diff}</span>`;
            row.find('td').eq(0).html(formatted);
            row.attr('data-updated-at', e.time);
        }
    });

});
