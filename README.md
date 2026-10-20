# Ulvi Case Study

A  Laravel backend for a character-based AI chat app. Users talk to a character, every reply costs tokens, and users buy more tokens through Stripe.

## How it works

Sending a message does not wait for the reply. The API saves the message, takes the most the reply can cost from the user's wallet and answers `202`. A queue worker asks the model for the reply, saves it, refunds the unused tokens and sends the reply to the browser over a WebSocket. If the reply fails, all of the tokens are refunded.

Every balance change is a row in the ledger with the balance before and after it. Balances are never overwritten. The wallet row is locked while it changes, so two requests can never spend the same tokens.

Tokens are bought with Stripe Checkout. Stripe reports the payment with a webhook, which is verified, stored and then processed by a queue job. Stripe can send the same webhook more than once, so the credit uses a key tied to the purchase and can only happen once.

Most of the code answers one question: what happens if this runs twice? A retried send returns the first message. A reply job that runs twice still saves one reply and refunds once. A webhook delivered three times credits once. Twenty requests spending from one wallet at the same moment never spend more than it holds.

## Built with

Laravel 13 and PHP 8.4. PostgreSQL stores the data and Redis holds the queue. Horizon runs the queue workers, Reverb sends the WebSocket events and Sanctum issues the API tokens. Payments use stripe-php and replies use the Anthropic SDK. Tests use Pest, and Larastan checks the types.

Without API keys, replies come from a fake model and payments from a local checkout page. The webhook code they go through is the real one.

## Running it

You need PHP 8.4, Composer, Node, PostgreSQL and Redis. `docker compose up -d` starts PostgreSQL and Redis.

```bash
composer setup
composer dev
```

Open <http://localhost:8000/demo> and sign in as `demo@ulvi.test` with the password `password`. Add `#flaky` to a message to make the first attempt fail, or `#fail` to make every attempt fail.

The race demo starts 20 processes that spend from the same wallet at once:

```bash
php artisan demo:race
php artisan demo:race --unsafe
php artisan ledger:verify
```

The first run lets exactly 5 of the 20 through. The unsafe run skips the lock, lets all 20 through, and the ledger check finds the difference.

## Tests

`composer check` runs Pint, Larastan and the tests. The tests run on PostgreSQL because row locks and database constraints are part of what they check. A contract test fails when a route or a response does not match `openapi.yaml`.

The reasons behind the design are in [DECISIONS.md](DECISIONS.md).
