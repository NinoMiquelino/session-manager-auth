## 🙋‍♂️ Autor

<div align="center">
  <img src="https://avatars.githubusercontent.com/ninomiquelino" width="100" height="100" style="border-radius: 50%">
  <br>
  <strong>Onivaldo Miquelino</strong>
  <br>
  <a href="https://github.com/ninomiquelino">@ninomiquelino</a>
</div>

---

# 🔐 Session Manager - Sistema de Autenticação Segura

![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue)
![SQLite](https://img.shields.io/badge/SQLite-3.x-green)
![TailwindCSS](https://img.shields.io/badge/Tailwind-CSS-orange)
![License](https://img.shields.io/badge/License-MIT-green)

Sistema completo de autenticação com gerenciamento avançado de sessões, auditoria de segurança e proteção contra ataques.

## ✨ Características

- **🔐 Autenticação Segura**: Login com validação e hash de senhas
- **📊 Dashboard Administrativo**: Monitoramento em tempo real
- **🛡️ Segurança Avançada**:
  - Rate Limiting
  - Auditoria completa
  - Gerenciamento de sessões
  - Proteção contra brute force
- **💾 Banco SQLite**: Configuração simples, zero dependências externas
- **📱 Interface Responsiva**: Design moderno com TailwindCSS

## 🚀 Começando Rápido

### Pré-requisitos
- PHP 8.0 ou superior
- SQLite3
- Extensão PDO para SQLite

### Instalação

1. **Clone o repositório**
```bash
git clone https://github.com/NinoMiquelino/session-manager-auth.git
cd session-manager-auth
```

1. Configure permissões

```bash
chmod 755 database/ logs/
```

1. Acesse o sistema

```bash
php -S localhost:8000
```

1. Credenciais de Demonstração

```
Usuário: admin
Senha: admin123
```

🏗️ Estrutura do Projeto

```
session-manager-auth/
├── index.html              # Página de login
├── login.php              # Processamento de login
├── dashboard.php          # Painel administrativo
├── logout.php            # Logout do sistema
├── config/
│   └── database.php      # Configuração do banco
├── classes/
│   ├── SessionManager.php # Gerenciador de sessões
│   ├── SecurityLogger.php # Sistema de auditoria
│   └── RateLimiter.php   # Proteção rate limiting
├── api/
│   └── sessions.php      # API para gerenciamento
├── database/
│   └── sessions.db       # Banco SQLite (criado automaticamente)
└── logs/
    └── security.log      # Logs de segurança
```

🔧 Configuração

Configuração do Banco de Dados

O sistema utiliza SQLite e cria automaticamente:

· Tabela de usuários<br>
· Tabela de sessões<br>
· Tabela de auditoria<br>
· Tabela de rate limiting

Personalização

Edite config/database.php para ajustar:

· Timeout de sessão<br>
· Limites de rate limiting<br>
· Configurações de segurança

🛡️ Funcionalidades de Segurança

Gerenciamento de Sessões

· Tokens únicos por sessão<br>
· Expiração automática (30 minutos)<br>
· Revogação remota de sessões<br>
· Monitoramento em tempo real

Rate Limiting

· 5 tentativas de login a cada 5 minutos<br>
· Bloqueio automático após excessos<br>
· Limpeza automática de registros antigos

Auditoria

· Log de todas as ações do sistema<br>
· Registro de IP e user agent<br>
· Dashboard de monitoramento<br>
· Exportação de logs

📊 Dashboard

O painel administrativo inclui:

· Estatísticas em Tempo Real: Sessões ativas, logs, tamanho do banco<br>
· Gerenciamento de Sessões: Visualize e revogue sessões ativas<br>
· Log de Atividades: Auditoria completa do sistema<br>
· Informações de Rede: IP do usuário, localização, etc.

🔌 API

Endpoints disponíveis:

· GET /api/sessions.php - Listar sessões ativas<br>
· POST /api/sessions.php?action=revoke - Revogar sessão

🚨 Monitoramento

O sistema monitora automaticamente:

· Tentativas de login falhas<br>
· IPs suspeitos<br>
· Comportamento anômalo<br>
· Uso de recursos

🔒 Melhores Práticas Implementadas

· ✅ Hash de senhas com password_hash()<br>
· ✅ Proteção contra SQL Injection<br>
· ✅ Rate Limiting<br>
· ✅ Tokens de sessão únicos<br>
· ✅ Validação de entrada<br>
· ✅ Headers de segurança<br>
· ✅ Logs de auditoria<br>
· ✅ Cleanup automático

---

Desenvolvido com ❤️ para projetos seguros

---

## 🤝 Contribuições
Contribuições são sempre bem-vindas!  
Sinta-se à vontade para abrir uma [*issue*](https://github.com/NinoMiquelino/session-manager-auth/issues) com sugestões ou enviar um [*pull request*](https://github.com/NinoMiquelino/session-manager-auth/pulls) com melhorias.

---

## 💬 Contato
📧 [Entre em contato pelo LinkedIn](https://www.linkedin.com/in/onivaldomiquelino/)  
💻 Desenvolvido por **Onivaldo Miquelino**

---
