# welcome-guest

Uma página de boas-vindas para servidores PHP que substitui o padrão *"It works!"*.

Exibe os últimos IPs que acessaram o servidor, com data/hora e user-agent.

---

## Prévia

```
welcome, guest
this server has been visited 7 times

your ip → 189.26.xx.xx — 2025-01-15 14:32:01

recent visitors
#   datetime              ip               user-agent
1   2025-01-15 14:32:01   189.26.xx.xx     Mozilla/5.0 ...
2   2025-01-15 13:10:44   177.84.xx.xx     curl/8.1.0
...
```

---

## Arquivos

```
welcome-guest/
├── index.php   → lógica + interface
├── ips.log     → gerado automaticamente (não versionado)
├── .gitignore
└── README.md
```

---

## Como usar

### Em um servidor Apache/Nginx com PHP

1. Clone o repositório na raiz do servidor:

```bash
git clone https://github.com/seu-usuario/welcome-guest.git /var/www/html
```

2. Certifique-se de que o PHP tem permissão de escrita na pasta para criar o `ips.log`:

```bash
chown www-data:www-data /var/www/html
# ou
chmod 755 /var/www/html
```

3. Acesse o servidor no navegador. O `ips.log` será criado automaticamente.

### Testar localmente com PHP embutido

```bash
cd welcome-guest
php -S localhost:8080
```

Acesse `http://localhost:8080` no navegador.

---

## Configuração

No topo do `index.php` há duas constantes:

| Constante    | Padrão | Descrição                          |
|--------------|--------|------------------------------------|
| `LOG_FILE`   | `./ips.log` | Caminho do arquivo de log     |
| `MAX_ENTRIES`| `50`   | Máximo de registros mantidos       |

---

## Segurança

- O `ips.log` é ignorado pelo Git (`.gitignore`) para não expor IPs públicos no repositório.
- Os dados exibidos na página passam por `htmlspecialchars()` para evitar XSS.
- Recomendado para uso em servidores pessoais ou de desenvolvimento.

---

## Licença

MIT
