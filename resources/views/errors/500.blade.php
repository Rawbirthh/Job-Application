@include('errors::layout', [
    'code' => '500',
    'title' => 'Something went wrong on our end',
    'message' => 'We hit an unexpected error while loading this page. Try again shortly — if it keeps happening, the reference below helps us track it down.',
    'icon' => 'alert',
])
