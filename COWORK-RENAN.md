# Trabalhando neste projeto com o Claude (Cowork)

Guia rápido pra conectar o Claude (modo Cowork, no app de desktop) a este projeto e começar a desenvolver com ele.

## Pré-requisitos

1. Ambiente local já instalado e o projeto rodando — siga o **`SETUP-RENAN.md`** primeiro (XAMPP, Composer, Node, Git, projeto clonado em `C:\xampp\htdocs\dashboard-faturamento`).
2. App do **Claude (desktop)** instalado, com o **modo Cowork** disponível.

## Passo 1 — Conectar a pasta do projeto

O Cowork só consegue ler e editar arquivos em pastas às quais você dá acesso.

1. Abra o Claude (desktop) e entre no **Cowork**.
2. **Conecte a pasta do projeto**: quando o Claude pedir pra selecionar uma pasta (ou no botão de conectar/adicionar pasta), escolha:

   ```
   C:\xampp\htdocs\dashboard-faturamento
   ```

   > Se você for trabalhar em outros projetos do `htdocs` também, pode conectar `C:\xampp\htdocs` inteiro — mas pro foco neste sistema, a pasta do projeto basta.
3. Se em algum momento o Claude disser que **não tem acesso** a um arquivo/pasta, peça pra ele **solicitar acesso à pasta** (ele abre o pedido de permissão) e aprove.

## Passo 2 — Dar o contexto ao Claude

A pasta já tem um arquivo **`CLAUDE.md`** que o Claude lê **automaticamente** ao abrir o projeto — ele descreve o sistema inteiro (stack, arquitetura, regras). Na dúvida, comece a conversa pedindo:

> "Leia o `CLAUDE.md` e o `SETUP-RENAN.md` desta pasta antes de começarmos. Vou desenvolver neste projeto Laravel."

Sugestão de **primeira mensagem** pra ele já entrar com tudo na cabeça:

> "Você é meu parceiro de desenvolvimento neste projeto. É um dashboard de faturamento em Laravel 12 que consome a API v3 do Tiny ERP. Leia o `CLAUDE.md` na raiz da pasta — ele tem todo o contexto, a arquitetura e as regras (principalmente: não conectar o ambiente local no Tiny real, e manter SQL portável MySQL/Postgres). Depois me confirme que entendeu e aguarde minha tarefa."

## Passo 3 — Como pedir o trabalho

O Claude consegue ler/editar os arquivos e rodar comandos no ambiente dele. Exemplos do que dá pra pedir:

- "Crie uma página de relatório mensal exportável em PDF."
- "Adicione testes pra calcular o delta e a matriz empresa × canal."
- "Implemente o sync via fila (queue) pra não estourar o limite de tempo."
- "Explique como funciona o `OrderSyncService` antes de eu mexer."

Peça pra ele rodar os comandos quando precisar (`composer`, `php artisan`, `npm run build`, etc.) — mas a aplicação roda no **seu XAMPP**, então alguns comandos (migrate, serve) você roda no seu próprio terminal.

## Regras de segurança (importante)

- **Revise o que o Claude muda** antes de commitar. Ele é ótimo, mas você é o dono do código.
- **Nunca conecte o ambiente local no Tiny real** (deixe `TINY_*` vazio no `.env`). Conectar/sincronizar com o Tiny derruba a produção no Cloud (rotação de token). Isso está no `CLAUDE.md` — o Claude já sabe, mas não force.
- **Trabalhe em branch + Pull Request.** `git push` no `main` faz deploy automático no Cloud. Crie um branch pra cada tarefa:
  ```
  git checkout -b nome-da-tarefa
  ```
- **Não commite o `.env`** (já está no `.gitignore`).

## Dúvidas frequentes

- **"O Claude não está vendo meus arquivos"** → reconecte a pasta `C:\xampp\htdocs\dashboard-faturamento` no Cowork e peça pra ele listar os arquivos.
- **"Mudei uma tela e o layout sumiu"** → rode `npm run build` (ou deixe `npm run dev` rodando) pra recompilar o Tailwind.
- **"Preciso de dados pra testar"** → o banco local começa vazio. Peça pro Claude criar um *seeder* de pedidos fictícios (sem tocar no Tiny real).

---

Resumo: conecta a pasta → o Claude lê o `CLAUDE.md` sozinho → manda a tarefa → revisa → commita num branch. Bom desenvolvimento!
