@extends('layouts.guest', ['title' => 'Set up'])

@section('content')
<div>
    <h1 class="title" style="font-size:22px">Create the owner account</h1>
    <div class="muted small" style="margin-top:4px">This page only works once, while no account exists. You can invite your team later.</div>
</div>
<form method="post" action="{{ url('/setup') }}" style="display:flex;flex-direction:column;gap:16px">
    @csrf
    <div class="field">
        <label for="workspace">Agency or company name</label>
        <input id="workspace" class="in" name="workspace" value="{{ old('workspace', 'Graphix') }}" required>
        @error('workspace')<div class="err">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label for="name">Your name</label>
        <input id="name" class="in" name="name" value="{{ old('name') }}" autocomplete="name" required>
        @error('name')<div class="err">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input id="email" class="in" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
        @error('email')<div class="err">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input id="password" class="in" type="password" name="password" autocomplete="new-password" minlength="10" required>
        <div class="hint">At least 10 characters.</div>
        @error('password')<div class="err">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" class="in" type="password" name="password_confirmation" autocomplete="new-password" required>
    </div>
    <button class="btn" type="submit">Create account</button>
</form>
@endsection
