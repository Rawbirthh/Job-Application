@include('errors::layout', [
    'code' => '429',
    'title' => 'A little too fast',
    'message' => "You've sent more requests than we allow right now. Take a short breather and try again in a moment.",
    'icon' => 'gauge',
])
