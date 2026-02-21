<?php

$a = 1;

?>
{{-- blade comment --}}
@php
    $b = 2; // php comment in blade
    $c = 3; /* php block comment in blade */
@endphp
@verbatim
    {{-- this shouldn't be stripped because verbatim --}}
@endverbatim

<script>
    const obj = {
        "key//1": "value",
        "key/*2*/": "value"
    };
    const tpl = `
        <!-- not an html comment -->
        ${obj['key//1']}
    `;
    const regex = /pattern\/\//g;
    const a = 1 / 2; // this is a comment
</script>

<style>
    .a { background: url(http://example.com/a.png); }
    /* this is a comment */
    .b { color: red; } // inline comment? (scss usually)
</style>

<!--[if IE]><p>IE only</p><![endif]-->
<!-- normal html comment -->
