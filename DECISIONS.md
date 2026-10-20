# Decisions

## The ledger is the record

Every balance change is a ledger row with the balance before and after it. The wallet keeps the current balance in a single row so it can be locked and read quickly. `ledger:verify` checks every night that the two agree, and the database refuses negative balances and edits to the ledger.

## Lock the wallet row

Every balance change starts with `SELECT ... FOR UPDATE` on the user's wallet inside a transaction. Other requests for the same user wait a few milliseconds. I chose this over optimistic locking because it needs no retry loop, and over a Redis lock because a database lock ends with the transaction.

## Reserve first, refund later

The cost of a reply is only known after it is written. So sending a message takes the most it can cost, and the job refunds what was not used. Charging afterwards would let a user start many replies with tokens for one.

## Keys name the thing, not the event

Ledger keys look like `message:12:refund` and `top-up:3`. The success path and the failure path of a reply use the same refund key, so a message cannot be refunded twice. Two different Stripe events can report one payment, so the key is the purchase and not the event id.

## Store webhooks first

The endpoint checks the signature, stores the event and answers 200 right away. A queue job does the work. If that job is lost, the next delivery from Stripe queues it again.

## The queue does the retries

The reply job tries three times with a short wait in between. The Anthropic SDK does not retry on its own, so there is one retry layer instead of two. Errors that cannot succeed, like a bad request, fail at once.

## No Cashier for now

A top-up is one Checkout call and one webhook, and I wanted to write that part myself. For subscriptions I would use Cashier and keep this webhook handling in front of it.

## PostgreSQL in tests

SQLite ignores `FOR UPDATE`, so a race could never make a test fail on it.

## What I would change

A reply job that runs twice calls the model twice. The result is still correct, but the second call costs money. I would let a worker claim the message with a single `UPDATE` first and skip the call if the claim fails.

Every message reserves 4,096 tokens for the reply. A user with 3,000 tokens cannot send anything, even though a reply usually costs a few hundred. I would lower `max_tokens` to what the user can afford.

The input estimate counts characters. A token count from the API would be exact, but it adds one call per message.
