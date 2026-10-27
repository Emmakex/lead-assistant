# Lead Assistant

AI chatbot and lead generation assistant for WordPress.

## Product principle

Lead Assistant is not a general-purpose chatbot. It is a focused conversational lead engine for WordPress: it understands the business, keeps conversations within scope, captures qualified leads, and stores the commercial context needed for follow-up.

## Product goals

- Three-step onboarding: connect AI, describe the business, choose the lead goal.
- No prompt engineering required for normal users.
- Conversation Governor keeps the assistant focused and redirects off-topic requests.
- Leads and conversations remain under WordPress administration.
- Core stays industry-agnostic; calculators and vertical behavior are added as actions/presets.
- Mobile-first public interface.

## Initial shortcode

`[lead_assistant]`

## Architecture rule

Core -> Conversation Governor -> Business configuration -> Actions -> Presets

The core must never contain mortgage-, real-estate-, insurance-, or other vertical-specific behavior.

## Status

Foundation in progress. SmartFinance is a reference implementation only; its vertical mortgage logic is not copied into the core.
