<!DOCTYPE html>
<html>
<head>
    <title>Status do Pagamento</title>
</head>
<body>
@if(session('success'))
    <p>Sucesso: {{ session('success') }}</p>
    @php
        $agreement = session('agreement');
        // Exibe os detalhes do acordo, você pode customizar conforme suas necessidades
        var_dump($agreement);
    @endphp
@elseif(session('error'))
    <p>Erro: {{ session('error') }}</p>
@endif
</body>
</html>
