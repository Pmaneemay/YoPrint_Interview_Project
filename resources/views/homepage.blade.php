<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Homepage</title>
    <link rel="stylesheet" href="{{asset('css/homepage.css')}}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.15.1/echo.iife.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
   <script>
        window.PUSHER_KEY = "{{ config('broadcasting.connections.pusher.key') }}";
        window.PUSHER_CLUSTER = "{{ config('broadcasting.connections.pusher.options.cluster') }}";
    </script>
    <script src="{{ asset('js/homepage.js') }}"></script>

</head>
<body>
    <div class="container">
    <div class="file-container" id="file-container">
        Select file/Drag and drop
        <button type="button" class="upload-btn" id="UploadBtn">Select File</button>
        <input type="file" id="file-input" accept=".csv,.txt">
    </div>
    <div class="file-table-container">
        <table id="files-table" class="display" style="width:100%">
            <thead>
            <tr>
                <th>Time</th>
                <th>File Name</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <!-- Rows dynamically populated -->
            </tbody>
        </table>
    </div>
</div>
</body>
</html>