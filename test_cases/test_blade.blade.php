{{-- Blade Test file - complex edge cases --}}
<!DOCTYPE html>
<html>
<head>
    {{-- Meta comment --}}
    <title>@yield('title', 'Default')</title>
    
    {{-- CSS with comments --}}
    <style>
        /* CSS comment */
        .class { color: red; }
        // Not valid CSS but might appear
    </style>
    
    {{-- JS with comments --}}
    <script>
        // JS comment
        const x = 1;
        /* block */
    </script>
</head>
<body>
    {{-- Header comment --}}
    <header>
        {{-- Nav --}}
        <nav>
            @if($showNav)
                {{-- If comment --}}
                <ul>
                    @foreach($items as $item)
                        {{-- Loop comment --}}
                        <li>{{ $item->name }}</li>
                    @endforeach
                </ul>
            @endif
        </nav>
    </header>
    
    {{-- Main content --}}
    <main>
        {{-- Include comment --}}
        @include('partials.sidebar')
        
        {{-- Section comment --}}
        <section>
            @php
                // PHP comment inside @php
                $var = 1; // inline
                /* block */
            @endphp
            
            {{-- Output comment --}}
            <p>{{ $content }}</p>
            
            {{-- Escaped output --}}
            <p>@{{ not blade }}</p>
            
            {{-- Raw HTML --}}
            {!! $html !!}
        </section>
        
        {{-- Components --}}
        <x-alert type="error">
            {{-- Slot comment --}}
            Error message
        </x-alert>
        
        {{-- Stack comment --}}
        @push('scripts')
            <script>
                // Pushed JS
                console.log('pushed');
            </script>
        @endpush
    </main>
    
    {{-- Footer --}}
    <footer>
        {{-- Copyright --}}
        <p>&copy; {{ date('Y') }}</p>
        
        {{-- HTML comment inside Blade -->
        <!-- Regular HTML comment -->
    </footer>
    
    {{-- Final scripts --}}
    @stack('scripts')
    
    <script>
        // Final JS
        const url = "http://example.com";
        /* block */
    </script>
</body>
{{-- End of file --}}
</html>
