# Gestão Financeira

Aplicação web de controle financeiro pessoal construída com **Laravel 11**, **Livewire 3** e **Volt**. Permite gerenciar contas, cartões de crédito, transações, orçamentos, investimentos e metas financeiras em um único painel, com gráficos, alertas proativos e importação/exportação de dados.

Interface 100% em português (pt-BR), responsiva (sidebar recolhível em telas pequenas) e com feedback visual via SweetAlert2 para todas as ações de sucesso, erro e confirmação.

## Funcionalidades

### Dashboard
- Cards de resumo: saldo total, receitas e despesas do mês, patrimônio investido.
- Gráficos (Chart.js): receitas x despesas dos últimos 6 meses e despesas por categoria.
- **Central de alertas**: avisa sobre faturas de cartão vencendo ou vencidas, transações pendentes atrasadas e metas com prazo próximo.
- **Resumo por e-mail**: os mesmos alertas são enviados diariamente por e-mail (job agendado `finance:send-alerts`) para quem tiver pendências em aberto.
- Lista das últimas transações, contas, cartões e investimentos ativos.

### Contas
- Cadastro de contas correntes, poupança, dinheiro em espécie, investimento ou outro tipo.
- Saldo atual calculado automaticamente a partir das transações (receitas, despesas e transferências).
- Ativar/inativar contas sem perder o histórico.

### Cartões de crédito
- Cadastro com limite, dia de fechamento e dia de vencimento.
- **Fatura do cartão**: tela por cartão que calcula automaticamente o ciclo de fechamento/vencimento, lista os lançamentos do período e permite **marcar a fatura como paga** (gera a transação de pagamento e libera o limite usado).
- Barra de limite utilizado x disponível.

### Transações
- Lançamento de receitas, despesas e transferências entre contas.
- **Forma de pagamento**: Pix, Débito, Crédito, Dinheiro, Boleto ou Outro — com troca automática entre conta/cartão conforme a forma escolhida.
- **Parcelamento no cartão de crédito**: divide uma compra em até 24x, criando uma transação por parcela e vinculando-as como uma série.
- **Recorrência**: transações que se repetem (semanal, mensal ou anual), gerando automaticamente as ocorrências futuras como pendentes.
- **Anexos**: upload de comprovante (PDF ou imagem) por transação.
- **Regras automáticas de categorização**: categoriza transações automaticamente quando a descrição contém uma palavra-chave cadastrada (ex.: "Uber" → Transporte).
- **Busca global** (na barra superior e na própria listagem), filtros por mês, tipo, categoria e forma de pagamento.
- **Importação de extrato em CSV com conciliação bancária**: mapeamento de colunas, detecção automática de formato e pré-visualização. Linhas do extrato que já correspondem a uma transação existente (mesma conta, tipo, valor e data próxima) conciliam essa transação em vez de duplicá-la; as demais são criadas já conciliadas.
- **Conciliação bancária**: cada transação pode ser marcada manualmente como conciliada ou não, com filtro dedicado e ação em massa para conciliar várias de uma vez.
- **Exportação em CSV** respeitando os filtros aplicados.
- **Ações em massa**: selecione várias transações para marcar como pagas/pendentes, aplicar uma categoria ou excluir de uma vez.
- Exclusão de uma ocorrência única ou de toda a série (recorrência/parcelamento).

### Categorias
- Categorias de receita e despesa, com cor personalizada.
- Painel de regras automáticas de categorização (ver acima).

### Ações em massa
- Contas, cartões de crédito, transações e metas permitem selecionar vários itens de uma vez.
- Transações: marcar como pagas/pendentes, conciliar, aplicar categoria ou excluir em lote.
- Contas: ativar, inativar ou excluir em lote.
- Cartões e metas: excluir em lote.

### Orçamentos
- Definição de limite mensal por categoria.
- Barra de progresso com aviso visual ao aproximar-se do limite.
- Alerta (toast) disparado ao lançar uma despesa que atinge 80% ou estoura o orçamento da categoria.

### Investimentos
- Cadastro de investimentos com tipos **totalmente customizáveis** pelo usuário (ex.: Renda Fixa, Ações, FIIs, Cripto, etc.).
- Distribuição da carteira por classe de ativo (gráfico de rosca) e por ativo individual.
- Cálculo automático de rentabilidade (R$ e %) por investimento e da carteira como um todo.
- Filtro por tipo de ativo.

### Metas financeiras
- Criação de metas com valor objetivo, valor já guardado e data alvo opcional.
- Registro rápido de aportes, com celebração ao atingir a meta.
- Barra de progresso e valor restante para cada meta.

### Relatórios
- Despesas por categoria (gráfico + detalhamento) e evolução de receitas x despesas.
- Filtro por mês/ano ou por **período personalizado** (data inicial e final).
- Exportação em CSV do período selecionado.

### Lixeira
- Contas, cartões, transações, categorias, orçamentos, investimentos e metas excluídos vão para uma lixeira (soft delete) em vez de serem apagados na hora.
- Restauração com um clique ou exclusão definitiva quando desejado.

### Conta e perfil
- Cadastro, login, verificação de e-mail, redefinição de senha e confirmação de senha.
- Edição de perfil, alteração de senha e exclusão de conta.

## Stack técnica

- **Backend**: PHP 8.2+, Laravel 11
- **Frontend reativo**: Livewire 3 + Volt (componentes single-file)
- **Estilo**: Tailwind CSS
- **Gráficos**: Chart.js
- **Alertas e confirmações**: SweetAlert2
- **Banco de dados**: MySQL
- **Testes**: PHPUnit (Feature tests cobrindo todos os módulos)

## Requisitos

- PHP 8.2 ou superior
- Composer
- Node.js e npm
- MySQL (ou outro banco suportado pelo Laravel)

## Instalação

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Configure a conexão com o banco de dados no arquivo `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

```bash
php artisan migrate
php artisan storage:link
npm run build
```

Para popular o banco com dados de exemplo (contas, categorias, transações e um usuário de demonstração), rode:

```bash
php artisan db:seed
```

> As credenciais do usuário de demonstração ficam definidas em `database/seeders/DatabaseSeeder.php` — não devem ser usadas em produção.

## Desenvolvimento

```bash
php artisan serve   # servidor local
npm run dev          # build de assets com hot reload
```

### Alertas por e-mail

O resumo diário de alertas financeiros é enviado pelo comando `php artisan finance:send-alerts`, agendado para rodar todo dia às 08h (ver `routes/console.php`). Para o agendamento funcionar em produção, configure o cron do servidor para chamar `php artisan schedule:run` a cada minuto. Em desenvolvimento, rode o comando manualmente ou use `php artisan schedule:work`. O canal de envio (`MAIL_MAILER`) é configurado no `.env`.

## Testes

O projeto conta com uma suíte de testes automatizados cobrindo autenticação, CRUD de todos os módulos, parcelamento, recorrência, anexos, importação/exportação, regras de categorização, alertas e fatura de cartão.

```bash
php artisan test
```

## Estrutura de rotas principais

| Rota | Descrição |
|---|---|
| `/dashboard` | Visão geral e alertas |
| `/contas` | Contas bancárias |
| `/categorias` | Categorias e regras automáticas |
| `/cartoes` | Cartões de crédito |
| `/cartoes/{cartao}/fatura` | Fatura de um cartão específico |
| `/transacoes` | Transações |
| `/transacoes/importar` | Importação de extrato CSV |
| `/orcamentos` | Orçamentos por categoria |
| `/investimentos` | Carteira de investimentos |
| `/metas` | Metas financeiras |
| `/relatorios` | Relatórios e exportação |
| `/lixeira` | Itens excluídos (restaurar ou remover definitivamente) |

## Licença

Projeto privado de uso pessoal, construído sobre o framework [Laravel](https://laravel.com), licenciado sob [MIT](https://opensource.org/licenses/MIT).
