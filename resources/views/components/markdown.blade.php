@props(['source'])

{{--
    The only place Markdown becomes HTML, and the only unescaped output in the app.
    Raw HTML in the source is stripped and unsafe links (javascript:, data: and so on) are blocked.
--}}
<div {{ $attributes->class('prose max-w-none dark:prose-invert prose-a:text-accent-dark prose-a:decoration-accent dark:prose-a:text-accent') }}>
    {!! Str::markdown((string) $source, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
</div>
