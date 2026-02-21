{}
<!DOCTYPE html>
<html>
<head>
    {}
    <title>@yield('title', 'Default')</title>
    
    {}
    <style>
                         
        .class { color: red; }
        // Not valid CSS but might appear
    </style>
    
    {}
    <script>
                     
        const x = 1;
                   
    </script>
</head>
<body>
    {}
    <header>
        {}
        <nav>
            @if($showNav)
                {}
                <ul>
                    @foreach($items as $item)
                        {}
                        <li>{{ $item->name }}</li>
                    @endforeach
                </ul>
            @endif
        </nav>
    </header>
    
    {}
    <main>
        {}
        @include('partials.sidebar')
        
        {}
        <section>
            @php
                // PHP comment inside @php
                $var = 1; // inline
                /* block */
            @endphp
            
            {}
            <p>{{ $content }}</p>
            
            {}
            <p>@{{ not blade }}</p>
            
            {}
            {!! $html !!}
        </section>
        
        {}
        <x-alert type="error">
            {}
            Error message
        </x-alert>
        
        {}
        @push('scripts')
            <script>
                            
                console.log('pushed');
            </script>
        @endpush
    </main>
    
    {}
    <footer>
        {}
        <p>&copy; {{ date('Y') }}</p>
        
        {}
    @stack('scripts')
    
    <script>
                   
        const url = "http://example.com";
                   
    </script>
</body>
{}
</html>
