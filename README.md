# Quick Reservation

Frontend simples em PHP, HTML, CSS e JavaScript, preparado para ler e gravar no MySQL por PDO. A aplicação não instala nem altera o banco de dados.

## Integração com o MySQL do projeto

O PHP precisa rodar na mesma rede Docker do MySQL. No `docker-compose.yml` da equipe, o serviço PHP pode usar este diretório como contexto:

```yaml
build: ./frontend
```

Configure no serviço PHP as variáveis abaixo. `DB_HOST` deve ser exatamente o nome do serviço MySQL no Compose. Não use `localhost` para apontar de um container PHP para outro container.

```yaml
environment:
  DB_HOST: ${DB_HOST}
  DB_PORT: ${DB_PORT:-3306}
  DB_NAME: ${DB_NAME}
  DB_USER: ${DB_USER}
  DB_PASSWORD: ${DB_PASSWORD}
```

Use o `.env.example` como referência para preencher o `.env` ao lado do `docker-compose.yml` (ou configure `env_file` apontando para ele). Não envie senhas para o GitHub. O phpMyAdmin pode continuar no serviço existente do colega; o PHP conecta ao MySQL, não à interface do phpMyAdmin.

## Modelo esperado

As consultas usam somente as tabelas e colunas informadas no modelo do projeto:

- `USUARIO (id_usuario, nome, email)`
- `RECURSO (id_recurso, nome, tipo, descricao)`
- `RESERVA (id_reserva, id_usuario, id_recurso, inicio, fim, status)`

O responsável da reserva é escolhido entre os registros existentes em `USUARIO`. A tabela `RECURSO` não tem localização ou capacidade no modelo atual, então a tela não inventa esses dados. Observações também não são gravadas porque não existe essa coluna no modelo informado.

## Reserva e conflito de horário

Antes de inserir uma reserva ativa, o PHP inicia uma transação e bloqueia a linha do recurso (`SELECT ... FOR UPDATE`). Em seguida verifica se já existe uma reserva `ATIVA` com intervalo sobreposto e grava os horários somente se estiverem livres. O bloqueio exige que as tabelas envolvidas usem um mecanismo transacional, normalmente InnoDB. Todo caminho que cria reservas deve adotar a mesma proteção; uma validação isolada no navegador não é suficiente.

Os estados armazenados permanecem `ATIVA`, `CANCELADA` e `CONCLUIDA`; a interface os apresenta em português.
