So we're buildin a cross platform that supports Mobile and Web 

Using tools like
- NativePHP
- FilamentPHP
- InertiaJS

There are two main parts to the project that will be served inside the NativePHP app
1. Signal [InertiaJS + FilamentPHP]
2. Studio [InertiaJS + FilamentPHP]

The third app Station is for the admin

The app launcher will be a blade view in NativePHP that allows the user switch between studio / signal session

Studio UI split:
- Inertia/Vue routes for Studio user work, especially chat and day-to-day workflow.
- Filament routes for Studio login and administrative views the Studio user needs.

Signal UI split:
- Inertia/Vue routes for customer chat and primary request flow.
- Filament routes for settings and admin-style panels on the customer side.

Signal orchestration model (product design direction, not locked policy):
- Candidate direction is one user-facing `conversation` per request context for the asker chatbox.
- Candidate direction is many internal `threads` per request/order lifecycle to handle agent switching and phase isolation.
- Threads can hold `agent_key`, `phase`, and `ai_conversation_id` memory.
- The active thread may change over time (for example `RequestAgent` to `OrderAgent`) while keeping one visible chat flow for the asker.


the studio needs the user to signup and onboard to KYC / KYB before they can access the studio

The signal app will be accessible to all users with a basic account

- users table will have three types system, device and person

- device users will have limited access to the signal app ... this are users that haven't authenticated (guest users)

- person users will have access to both signal and studio apps 

- a device user upgrades to a person user by socialite login with Google / Apple 

So overview for now 

On signal ... users are here to make a request ... and when accepted it becomes an order  

On studio ... users here are those that accept orders and deliver services 

studio users will have profiles that they create to showcase their skills ... and pick from categories of services they want to offer

profiles must be approved by admin before they can go live on the signal marketplace 


So a typical flow of the platform

- Person User / Device User comes to the signal app and searches for profiles that match their needs and then makes a request to that profile 

- The profile owner gets notified of the request and can accept or reject it 

- If accepted ... the profile owner makes a quote for the request ... this basically a logging of what they will do, or can do , or can do for the request 

- If person user accepts the quote ... it becomes an booked order

- after this stage ... billing can be done ... either full or partial payment

- the profile owner now does assestment of the order ... which is optional ... if there's an assestement, the order assetment must be acknowledged by the person user before work can begin

- after this stage ... billing can be done ... either full or partial payment

- the person profile goes ahead to deliver the service ... online / offline 
   - there's a place on the studio for logging (allows photo, video, audio and text uploads) ... this is where the profile owner logs their work progress

- profile owner marks the order as completed 

- person user gets notified of completion and can review the work done

- person user can either accept the work or dispute it

- after this stage ... billing can be done ...  full payment or balance payment of partial payment

- if accepted ... the order is marked as fulfilled 

- both parties can rate each other

## Signal Fulfillment Flows (Design Exploration)

The following are product flow candidates to evaluate and refine:

- `ubuy` candidate:
- Asker targets a specific profile/tasker.
- Request starts with an intake thread using `RequestAgent`.
- Quote/booking/fulfillment likely stays bound to that selected profile unless reassigned.

- `upwork` candidate:
- Asker creates an open request.
- Multiple profiles can express interest and submit quotes/bids.
- Asker selects one quote to book, then flow can switch into fulfillment with `OrderAgent`.

- `uber` candidate:
- Asker creates a request without selecting a worker.
- System may auto-assign the best matching profile using availability + matching rules.
- After assignment, flow can proceed to quote or direct booking based on service configuration.

Thread usage (working design hypothesis):

- Main thread begins at request intake (`RequestAgent`).
- Additional threads can be used for scoped phases (for example negotiation, booking, fulfillment, disputes).
- A single request context may own multiple threads while preserving one primary user-facing conversation.
- Final rules for thread creation/switching remain open pending product decisions.

## Database & Migrations

Since the project is a monolith deployed on both the Server (MySQL/PostgreSQL) and Mobile (SQLite via NativePHP), we use a conditional migration strategy in `AppServiceProvider`:

- **Server Migrations:** Located in `database/migrations/server`. These contain schemas for the central database (Users, Profiles, Orders, etc.).
- **Mobile Migrations:** Located in `database/migrations/native`. These contain schemas for the local device database.
- **Shared Migrations:** Located in `database/migrations`.

The `AppServiceProvider` detects the environment using `config('nativephp-internal.running')` and loads the appropriate paths:

```php
if (config('nativephp-internal.running')) {
    $this->loadMigrationsFrom(database_path('migrations/native'));
} else {
    $this->loadMigrationsFrom(database_path('migrations/server'));
}
```
