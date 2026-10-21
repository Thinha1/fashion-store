{{-- Hidden inputs that carry the current filters through a GET form. --}}
@foreach ($query as $name => $value)
    @foreach ((array) $value as $item)
        <input type="hidden" name="{{ is_array($value) ? $name.'[]' : $name }}" value="{{ $item }}">
    @endforeach
@endforeach
