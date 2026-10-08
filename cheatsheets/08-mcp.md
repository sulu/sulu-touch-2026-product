# Step 8: An AI client manages products (MCP)

**Branch** `step-08-mcp`

**Goal** Let an AI create a draft product, with the rights of a Sulu user.

## Do

```bash
composer require sulu/mcp-bundle:1.0.x-dev
```

`config/packages/sulu_mcp.yaml`
```yaml
sulu_mcp:
    dangerous_tools:
        product_write: true      # create and update drafts; publish stays off
```

```bash
NODE_EXTRA_CA_CERTS=~/.symfony5/certs/default.crt \
  claude mcp add --transport http sulu https://127.0.0.1:48400/admin/mcp
```

**Prompt**
> Create the sticker "Rubber Duck Sticker" (code ST-9001) in the family Accessories, made of vinyl, a limited edition, with the URL /products/rubber-duck-sticker and the main webspace website, as a draft.

## Live

The code of this step on top of the previous step, so the IDE shows exactly its changes; then its packages, assets and data:

```bash
git checkout -q -f step-07-catalogue-search && git checkout step-08-mcp -- .
bin/data 8
```

## Show

- Login as `ai-editor` / `ai-editor`, then the consent screen.
- Name the URL and the webspace in the prompt: the tool sets neither by itself.
- `ai-editor` may view the webspace `website` (role permission `sulu.webspaces.website`), otherwise the admin preview fails.
- Before the talk: `config/routes.yaml` imports only `App\Controller`, and `bin/reset-demo` has made the JWT keys (`config/jwt/`, not in git).

## If it goes wrong

Show a recording of the same prompt. Typos: `git checkout -f step-08-mcp` (instant, keeps the data). Stuck: `bin/stand 8 force` (about 30 seconds).
