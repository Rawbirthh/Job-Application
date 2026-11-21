@include('errors::layout', [
    'code' => '403',
    'title' => "You don't have access to this page",
    'message' => "Your account doesn't have permission to open this area. If you're here to post a job, use an approved employer account — otherwise, there are plenty of roles waiting to be explored.",
    'icon' => 'lock',
])
