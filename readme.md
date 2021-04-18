### Instalação

```bash
$ composer install
```

Inicie o servidor:

```bash
$ php server.php
```

Inicie o cliente:

```bash
$ php client.php
```

### Instruções

Acesse no navegador **localhost:8081** para acessar o cliente, e **localhost:8081/server** para acessar a visão geral do servidor.
Pode abrir o cliente em várias abas, cada aba será um usuário diferente.

**Nota:** ao fazer o download de um arquivo, a janela perguntando o local de download pode não ser abertar pois depende das configurações do seu navegador.

### Requisitos e bibliotecas utilizadas
- [PHP](https://www.php.net) versão > 7.1
- [Composer](https://getcomposer.org): Gerenciado de bibliotecas do PHP
- [Ratchet](https://github.com/ratchetphp/Ratchet): Biblioteca em PHP para fazer o servidor de WebSockets
- [ReactPHP](https://reactphp.org): Biblioteca em PHP para rodar o cliente (também utilizado como dependência do Ratchet)
- [MonoLog](https://github.com/Seldaek/monolog): Biblioteca em PHP para salvar logs
- [Bootstrap](https://getbootstrap.com/) versão 4.6: Framework HTML/CSS
- [Vue.js](https://vuejs.org) versão 2.6.12: Framework JavaScript
- [Vue Timeago](https://github.com/runkids/vue2-timeago) versão 2.6.12: Extensão para Vue.js para mostrar o tempo de envio das mensagens
- [DiceBear Avatars](https://avatars.dicebear.com): Gerador de avatares pelo nome do usuário
- [Snippet Facebook Messenger](https://www.bootdey.com/snippets/view/facebook-messenger-chat): Estilo do bootstrap utilizado para o chat

