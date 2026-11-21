@include('errors::layout', [
    'code' => '419',
    'title' => 'Your session timed out',
    'message' => 'For your security, this page expired after a period of inactivity. Sign in again to pick up where you left off.',
    'icon' => 'clock',
])
