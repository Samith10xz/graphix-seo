@extends('layouts.guest', ['title' => 'Sign in'])

@section('content')
<div>
    <h1 class="title" style="font-size:22px">Sign in</h1>
    <div class="muted small" style="margin-top:4px">SEO planning and site audits for your clients.</div>
</div>
<form method="post" action="{{ url('/login') }}" style="display:flex;flex-direction:column;gap:16px">
    @csrf
    <div class="field">
        <label for="email">Email</label>
        <input id="email" class="in" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        @error('email')<div class="err">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input id="password" class="in" type="password" name="password" autocomplete="current-password" required>
    </div>
    <label class="chk"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
    <button class="btn" type="submit">Sign in</button>
</form>
@endsection
