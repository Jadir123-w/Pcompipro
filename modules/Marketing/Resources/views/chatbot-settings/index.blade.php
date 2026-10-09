@extends('tenant.layouts.app')

@section('content')
  <tenant-marketing-chatbot-settings-index></tenant-marketing-chatbot-settings-index>

  {{--
    Tester interno del chatbot.

    El widget se sirve desde /assets/chatbot/widget.js y monta un botón
    flotante "Chat" abajo a la derecha. Sirve para que la ejecutiva o la
    persona desarrolladora puedan probar el bot sin abrir un sitio externo
    cada vez que ajustan un setting de Config Chatbot.

    Este reemplaza el tester que vivía en la página legacy /marketing/chatbot
    (eliminada en PR #231).
  --}}
  <script src="/assets/chatbot/widget.js"></script>
@endsection
