{{-- Wraps any <table> with the horizontal-scroll container every data table
     needs, so a wide table degrades to a scrollable strip instead of
     breaking page layout or forcing text to wrap illegibly. --}}
<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <table class="w-full min-w-[560px] text-left text-sm">
        {{ $slot }}
    </table>
</div>
