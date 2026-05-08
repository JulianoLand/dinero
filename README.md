# Dinero - Controle Financeiro Colaborativo

Aplicação web PHP para controle financeiro de casas/grupos, utilizando SQLite.

## Como testar localmente

1. Certifique-se de ter PHP 8+ instalado
2. Navegue até a pasta do projeto
3. Execute: `php -S localhost:8000`
4. Abra no navegador: `http://localhost:8000`

## Estrutura do projeto

- `index.php` - Arquivo principal da aplicação
- `inc/db.php` - Conexão e funções do banco SQLite
- `inc/auth.php` - Autenticação e sessões
- `assets/` - CSS e JavaScript
- `data/` - Arquivo SQLite (criado automaticamente)

## Funcionalidades

- Cadastro e login de usuários
- Criação de casas/grupos financeiros
- Controle de permissões (Admin, Editor, Visualizador)
- Cadastro de receitas e despesas
- Filtros por período, tipo e status
- Interface responsiva

## Implantação no InfinityFree

1. Faça upload de todos os arquivos para o diretório público do InfinityFree
2. Certifique-se de que a pasta `data/` tenha permissões de escrita
3. A aplicação criará o banco automaticamente na primeira execução

## Requisitos

- PHP 8+
- SQLite3 (habilitado por padrão no PHP)
- Navegador moderno