# Step 9: Request for publishing

**Branch** `step-09-review-workflow`

**Goal** A product needs a review before it goes live. This is only configuration.

## Do

`config/packages/sulu_content.yaml`
```yaml
sulu_content:
    request_workflows:
        default:
            resources: [products]
            required_user_approvals: 1
            pre_validators:
                seo_required: { fields: [title, description] }
```

`config/routes/sulu_admin.yaml`
```yaml
sulu_content_api:
    resource: "@SuluContentBundle/config/routing_admin_api.yaml"
    prefix: /admin/api
```

The product bundle already ties the review permission to `sulu.product.products`, so no security config is needed.

**Flow:** ai-editor "Save and request for publish" (SEO empty: refused) → fill SEO → request → locked → admin "Review", "Approve" → ai-editor "Publish".

## Live

The code of this step on top of the previous step, so the IDE shows exactly its changes; then its packages, assets and data:

```bash
git checkout -q -f step-08-mcp && git checkout step-09-review-workflow -- .
bin/data 9
```

## Show

- The button is on the Content, SEO and Excerpt tabs, not on Details.
- As admin the Review button shows after a page reload; open the product's Content tab fresh.

## If it goes wrong

Typos: `git checkout -f step-09-review-workflow` (instant, keeps the data). Stuck: `bin/stand 9 force` (the finished step with its data, about 30 seconds).
