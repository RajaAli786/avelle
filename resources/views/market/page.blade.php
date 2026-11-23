@extends('layouts.market')
@section('title', $page->title . ' · Avelle')
@push('head')
    <link rel="stylesheet" href="{{ asset('information.css') }}?v=1">
@endpush
@php
    $demo = str_starts_with($page->body, '[DEMO CONTENT]');
    $body = $demo ? trim(substr($page->body, 14)) : $page->body;
    $blocks = preg_split('/^##\s+/m', $body);
    $intro = trim(array_shift($blocks));
    $helpPages = \Illuminate\Support\Facades\DB::table('pages')->where('active', true)->get();
@endphp
@section('content')
    <div class="shell breadcrumbs"><a href="{{ url('/') }}">Home</a><span>/</span><span>{{ $page->title }}</span></div>
    <section class="shell information-page">
        <header class="info-hero">
            <div><span class="eyebrow">THE LITTLE DETAILS, ALL TOGETHER</span>
                <h1>{{ $page->title }}</h1>
                <p>{{ $intro }}</p>
                @if ($demo)
                    <span class="info-demo">Demo content · Owner will update before launch</span>
                @endif
            </div>
            <span class="info-flower" aria-hidden="true">✳</span>
        </header>
        <div class="info-layout">
            <aside class="info-nav" aria-label="Information pages"><span>YOUR AVELLE GUIDE</span>
                @foreach ($helpPages as $link)
                    <a href="{{ url('/pages/' . $link->slug) }}"
                        @if ($link->id === $page->id) aria-current="page" @endif>{{ $link->title }} <span
                            aria-hidden="true">↗</span></a>
                @endforeach
            </aside>
            <div class="info-main {{ $page->slug === 'contact' ? 'info-main-contact' : '' }}">
                @if ($page->slug === 'about')
                    <div class="info-values">
                        <div><b>01 / DISCOVER</b>
                            <p>Find your everyday favourites.</p>
                        </div>
                        <div><b>02 / MAKE IT YOURS</b>
                            <p>Your style. Your own pace.</p>
                        </div>
                        <div><b>03 / STAY CONNECTED</b>
                            <p>Orders and support in one place.</p>
                        </div>
                    </div>
                @endif
                <div class="info-sections">
                    @foreach ($blocks as $block)
                        @php
                            $parts = explode("\n", trim($block), 2);
                        @endphp
                        @if ($page->slug === 'faq')
                            <details class="info-faq">
                                <summary>{{ $parts[0] }}</summary>
                                <div class="info-copy">{{ trim($parts[1] ?? '') }}</div>
                            </details>
                        @else<section class="info-section">
                                <h2>{{ $parts[0] }}</h2>
                                <div class="info-copy">{{ trim($parts[1] ?? '') }}</div>
                            </section>
                        @endif
                    @endforeach
                </div>
                @if ($page->slug === 'contact')
                    <section class="info-contact">
                        <div><span class="eyebrow">WE’RE HERE TO HELP</span>
                            <h2>Send us a little note.</h2>
                            <p>Fields marked * are required.</p>
                            @if ($settings['support_email'])
                                <p><a href="mailto:{{ $settings['support_email'] }}">{{ $settings['support_email'] }}</a>
                                </p>
                                @endif @if ($settings['support_phone'])
                                    <p>{{ $settings['support_phone'] }}</p>
                                    @endif @if ($settings['business_address'])
                                        <p>{{ $settings['business_address'] }}</p>
                                    @endif
                        </div>
                        <form class="form-card" method="POST" action="{{ url('/contact') }}">@csrf<label>Your name *<input
                                    name="name" value="{{ old('name', auth()->user()?->name) }}" required maxlength="100"
                                    autocomplete="name"></label><label>Email address *<input name="email" type="email"
                                    value="{{ old('email', auth()->user()?->email) }}" required maxlength="200"
                                    autocomplete="email"></label><label>Subject *<input name="subject"
                                    value="{{ old('subject', request('subject')) }}" required maxlength="150"
                                    placeholder="How can we help?"></label><label>Message *
                                <textarea name="message" required minlength="10" maxlength="5000" rows="5"
                                    placeholder="Tell us a little more. Include your order reference, if applicable.">{{ old('message') }}</textarea>
                            </label>
                            <div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1"
                                        autocomplete="off"></label></div><button class="pink-button">Send support request
                                →</button>
                        </form>
                    </section>
                @else<div class="info-help">
                        <div>
                            <h2>Still have a question?</h2>
                            <p>Our support form is a good place to start.</p>
                        </div><a class="pink-button" href="{{ url('/pages/contact') }}">Let’s talk ↗</a>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
