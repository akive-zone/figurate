- So this product is a platform for finding agents (workers / handlers)

- The callers / workers follow a fufillment process

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
2. User app gives recommendations / suggestions of different artisans and bio of their experience. The user can select the artisan he wants and the location details are
    shared to artisan.
3. The artisan gives a quote; what he thinks the problem is and possibly repairs (optional) -> later
4. The artisan comes to the user house/location and the artisan does a full assessment on the problem and states it on the app and the possible tools that will be bought.
    * This is drafted and sent to the user.
5. The user acknowledges what the artisan described and the acknowledgment is more like signal.
6. The user is given a bill of estimate (plus total addon fees). Then has to make payment - once user makes payment, payment is verified by the app.
7. The Artisan begins work, sourcing products he needs to get it done, and that it is properly done to the best way.
8. The artisan declares the work as done and the customer...
9. The customer rates the Artisan and selects the work as done.

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
