## Log 1
FLOW of Fullfillment:
   * Enquiry
   * Quote
   * Booking
   * Assessment
   * Acknowledge
   * Billing / Processing
   * Track / Trace (Quality Control)
   * Settlement

1. User comes on app to make a request
    * Submits the artisan he/she needs
    * Submit a picture or description of issues.
2. User app gives recommendations / suggestions of different artisans and bio of their experience. The user can select the artisan he wants and the location details are shared to artisan.
3. The artisan gives a quote; what he thinks the problem is and possibly repairs (optional) -> later
4. The artisan comes to the user house/location and the artisan does a full assessment on the problem and states it on the app and the possible tools that will be bought.
    * This is drafted and sent to the user.
5. The user acknowledges what the artisan described and the acknowledgment is more like signal.
6. The user is given a bill of estimate (plus total addon fees). Then has to make payment - once user makes payment, payment is verified by the app.
7. The Artisan begins work, sourcing products he needs to get it done, and that it is properly done to the best way.
8. The artisan declares the work as done and the customer...
9. The customer rates the Artisan and selects the work as done.

## Log 2
So i am thinking a chat like flow for a request fulfillment system.

A customer makes a request in a conversation like flow ... and in there they can find relevant agents or mention the agent they intend to contact.

Types of Agents:
- RequestAgent
This agent is the one that receives the user request and then passes off to the enquiry agent

- EnquiryAgent 
this agent asks for the user service, and if the service requires a location, it then asks for the user location (which checks if the service is available in that location).

 Then it asks for a picture or description of the issue. Then it gives recommendations of different artisans and bio of their experience. The user can select the artisan he wants and the location details are shared to artisan.

Then we can ask for task options ... How big is the task? How long do you think it will take? Do you have a budget for this? Then we can ask for the time frame ... when do you want this done?

- QuoteAgent

This agent will be what can for the quote from the worker and then we can ask for the assessment from the artisan and then we can ask for the acknowledgment from the user and then we can ask for the billing / processing from the user and then we can ask for the track / trace from the artisan and then we can ask for the settlement from the user.

- AssessmentAgent 

#### Fulfillment Flow Patterns (Design Exploration)

The following are request-routing patterns to evaluate and refine:

- Directed request pattern:
- Asker targets a specific profile/tasker.
- Request starts with an intake thread using `RequestAgent`.
- Quote/booking/fulfillment likely stays bound to that selected profile unless reassigned.

- Open marketplace pattern:
- Asker creates an open request.
- Multiple profiles can express interest and submit quotes/bids.
- Asker selects one quote to book, then flow can switch into fulfillment with `OrderAgent`.

- Assisted assignment pattern:
- Asker creates a request without selecting a worker.
- System may suggest or assign the best matching profile using availability + matching rules.
- After assignment, flow can proceed to quote or direct booking based on service configuration.

Thread usage (working design hypothesis):

- Main thread begins at request intake (`RequestAgent`).
- Additional threads can be used for scoped phases (for example negotiation, booking, fulfillment, disputes).
- A single request context may own multiple threads while preserving one primary user-facing conversation.
- Final rules for thread creation/switching remain open pending product decisions.


## Log 3: 
- Date- 13-02-2026

What i am looking at is agnostic system for channel <-> post <-> thread ... where thread is sort of like a session of messages happening 

Channel is like the main space here ... for the users ... it contains alot of threads ... By default when a channel is opened it contains no thread 

na the active thread is based on which user is on that channel

the owner/member/asker user can be on a channel 

then there's the worker/tasker user ... which can only act on a particular thread in the channel 

Now this agnostic platform has several usecase, one is request fullfilment

Earlier i described this usecase into 3 routing patterns

- directed request ... An approach where an asker user opens a channel with a specific profile or profiles in mind to carry out a request

- assisted assignment ... An approach where a user opens a channel and chats with the Request agent to shape the request before the system suggests or assigns the next best worker path

- open marketplace ... An approach where a user opens a channel, creates a request, and then opens it up for multiple profiles to bid or quote before selecting one

The key thing is a how the channel stands in as the entrypoint ... like the topic and then we have several posts on the topic and then the relative threads handling the discussion either discussion with the robots (ai) or humans (behind the profiles)

Some human thread would have robot observers for carrying out action and doing things ... as we build and understand the usecases we will handle it

NOTE:
  - Channel = long-lived context/entrypoint
  - Thread = active session/workstream
  - Post = durable domain events/artifacts
 
The goal is knowing when to orchesterate creating a new thread and which agent should be the one responding to a conversation (This is similar to popular systems like cursor or claude, knowing when to move from plan to build or debug mode within a session). This ansers the gap 5 ... which is what we need to decide


## Log 4:
- Case A: Thread where the user is talking to an agent (presenter prompting)
- Case B: Thread where the multiple user (group) is talking to an agent (presenter prompting)
- Case C: Case A but with multiple agent as handler
- Case D: Case B but with multiple agent as handler
- Case E: Thread where the user is talking to another user (observer prompting if allowed)
- Case F: Thread where the user is talking to multiple user (observer prompting if allowed)

Observer prompting will always assume multiple observer agent can exist in a thread conversation


# Log 5:
- Date: 25-02-2026

- 1. Cameo.com
- 2. Taskrabbit.com
- 3. Backstage.com
- 4. handy.com
- 5. Chowdeck.com

# Log 6:
- Date: 02-03-2026

## Explorer POV: Navigating the Figurate Interface

From an explorer's perspective, Figurate is a dynamic project workspace organized under a single **Channel**.

### 1. Landing: The "Agenda" (Index Page)
- **The Entrypoint:** A minimal interface with a large composer box ("What’s on the agenda today?").
- **The Goal:** To move the user from an abstract idea to a concrete **Channel**.

### 2. Contextualization: The "Channel" (Show Page)
- **The Sidebar:** Displays a history of "Chats" (Channels) and their nested "Threads" (active work sessions).
- **The Timeline (Main Feed):** A central "source of truth" rendering **Posts**—markdown-formatted artifacts like assessments, quotes, or project updates. It provides a persistent, high-level summary of achievement.

### 3. Interacting: The Dual-Panel Flow
- **The AI Assistant (Sliding Panel):** A private workspace for planning, asking questions, and providing details (e.g., photos). This is where `EnquiryAgent` or other agents interact with the user.
- **The Peer Chat (Floating Bubble):** A separate space for **human-to-human** communication with artisans or workers. The AI observes from its panel without interfering in the direct conversation.

### 4. Technical Deep-Dive: The "Embed Panel"
- **The Manage MCP Button:** Allows users to slide out a management interface (iframe) to connect external Model Context Protocol (MCP) servers to the current channel, plugging in new capabilities (like Google Maps or database searchers).

### 5. The "Thread" Navigator
- **Branching Work:** Users can create new **Threads** for specific sub-problems (e.g., "Finding a part" vs "Main repair") while maintaining the shared context of the overall Channel.

