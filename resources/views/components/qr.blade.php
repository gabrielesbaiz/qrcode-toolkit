{{--
    Inline SVG QR code.

    The markup comes from the renderer and is already escaped, so it is
    emitted unescaped on purpose. With `bare` the SVG goes straight into the
    document with no wrapper, which is what you want inside a <figure> or
    when you are styling the <svg> element directly.
--}}
@php($markup = $markup())
@if ($bare)
    {!! $markup !!}
@else
    <span {{ $wrapperAttributes()->merge(['class' => 'qrcode-toolkit', 'style' => 'display:inline-block;line-height:0']) }}>
        {!! $markup !!}
    </span>
@endif
