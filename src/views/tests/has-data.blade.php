<h1>Data Test PDF</h1>

<p>
    This is a test PDF used for Unit test loading.
</p>

<p>
    It requires some data
</p>

<h2>@{{ $list }} (Array)</h2>
<ul>
    @foreach($list as $item)
        <li>{{ $item }}</li>
    @endforeach
</ul>
