# Requirements Document - AI Sales Coach (SalesCoach Pro)

## Project Overview

SalesCoach Pro is a multi-tenant SaaS application that helps sales teams practice and improve their sales skills through AI-powered roleplay conversations. Sales reps practice calls against AI-driven prospect personas with configurable personalities, industries, and objection styles. Sales managers configure prospects, products, scoring rubrics, and review team performance analytics. Built on an HTMX/PHP/Bootstrap LAMP stack using Retell AI for voice-based roleplay sessions.

**Competitive Context:** This application competes in the AI sales coaching space alongside tools like Hyperbound, PitchMonster, Second Nature, and Highspot. Key differentiators include self-hosted LAMP simplicity, Retell AI voice integration, and configurable prospect persona builders with per-organization customization.

---

## 1. User Roles & Multi-Tenancy

### Organizations (Tenants)
- Each organization operates independently with its own prospects, products, agents, rubrics, and users
- Organization data is isolated via `org_id` foreign keys
- An organization is created during the first admin user's registration

### Role: Sales Manager (`admin`)
- Full CRUD on prospects, products, agents, scoring rubrics, and scoring categories
- View all team members' practice session history and scores
- Access team-wide analytics dashboard with aggregated performance metrics
- Manage organization members (invite, remove, change roles)
- Configure Retell AI agent settings (voice, LLM, prompts)
- Create and assign practice scenarios/modules to reps
- Review and annotate individual session transcripts

### Role: Sales Rep (`user`)
- Browse available prospects and products
- Initiate practice roleplay sessions (voice calls via Retell AI)
- View own session history, transcripts, and scores
- Access personal performance dashboard with trends over time
- View leaderboard position within their organization
- Access assigned practice modules/scenarios

### Role: Super Admin (`super_admin`)
- System-wide administration across all organizations
- Manage global settings, API keys, and system configuration
- Access system health and usage analytics

---

## 2. Core Domain Entities

### 2.1 Prospects (AI Buyer Personas)
Prospects represent fictional companies/buyers that sales reps practice selling to. Each prospect defines the context for a roleplay scenario.

**Fields (from existing schema + enhancements):**
- `id`, `org_id`, `user_id` — ownership
- `name` — company name (e.g., "Acme Manufacturing")
- `prospect_name` — buyer contact name (e.g., "Sarah Chen")
- `prospect_role` — buyer job title (e.g., "VP of Operations")
- `prospect_personality` — personality archetype (e.g., "Busy & skeptical", "Analytical & detail-oriented", "Friendly but indecisive", "Aggressive negotiator")
- `prospect_talkativeness` — Low / Medium / High
- `prospect_communication_style` — free text describing conversational behavior
- `business_type` — company type/vertical
- `industry` — industry vertical for contextual objections
- `company_size` — employee/revenue range
- `current_solution` — what they currently use (competitor context)
- `tools_used` — existing tech stack
- `recent_events` — contextual triggers (e.g., "Just raised Series B", "New CTO hired")
- `lead_volume` — volume context for sizing conversations
- `description` — detailed background narrative for AI prompt construction
- `image` — avatar/logo URL
- `stub` — URL-safe slug
- `agent_id` — linked Retell agent ID

**Prospect Agents (sub-personas):**
Each prospect can have multiple contact personas via `prospect_agents`:
- `agent_name`, `agent_description`, `agent_prompt`
- `agent_role` — caller/gatekeeper/decision-maker/influencer
- `default_temperament` — friendly/neutral/hostile/skeptical
- `product_knowledge` — 1-10 scale of how much they know about your product category
- `technical_level` — 1-10 scale of technical sophistication

### 2.2 Products
Products represent what the sales rep is selling during a practice session. Product context feeds into the AI agent's system prompt so the prospect can ask relevant questions.

**Fields (from existing schema):**
- `id`, `org_id`, `user_id` — ownership
- `name` — product/service name
- `product_category` — grouping label
- `prompt` — detailed product description, features, pricing, competitive positioning for AI context
- `knowledgebase` — reference to knowledge base document/URL

### 2.3 Agents (Retell AI Voice Agents)
Agents are the Retell AI voice configurations used during practice calls.

**Fields (from existing schema):**
- `id`, `org_id` — ownership
- `name` — display name
- `retell_agent_id` — Retell platform agent ID
- `description` — purpose/notes
- `image` — avatar URL
- `voice_name` — Retell voice selection
- `llm` — LLM model used (e.g., "gpt-4o", "claude-sonnet")

### 2.4 Practice Sessions (`user_agent_session`)
Each practice call generates a session record with transcript and scoring.

**Fields (from existing schema + enhancements):**
- `id`, `session_id` — identifiers
- `job_id` — linked async job if applicable
- `prospect_id` — which prospect persona was used
- `agent_id` — which Retell agent handled the call
- `product_id` — which product was being sold
- `transcript` — full conversation transcript (longtext)
- `quality_score` — overall AI-generated score (decimal 0-10)
- `critique_count` — number of improvement areas identified
- `severity` — overall severity of issues found
- `duration_ms` — call duration in milliseconds
- `agent_sentiment` — AI's assessment of prospect sentiment at call end
- `technical_level` — technical complexity of the conversation
- `call_type` — inbound/outbound/discovery/demo/objection-handling/closing

### 2.5 Scoring System

**Scoring Categories (`scoring_categories`):**
Manager-defined evaluation dimensions. Examples:
- Opening & Rapport Building
- Discovery & Needs Assessment
- Value Proposition Delivery
- Objection Handling
- Closing & Next Steps
- Product Knowledge
- Active Listening
- Talk-to-Listen Ratio

**Scoring Rubrics (`scoring_rubric`):**
Detailed criteria within each category defining what constitutes each score level (1-5 or 1-10). Managers define these per organization so AI feedback aligns with their sales methodology (SPIN, Challenger, MEDDIC, Sandler, etc.).

### 2.6 Business Context (`business_context`)
Organization-level prompt sections that provide AI agents with company-specific context:
- `section_type` — e.g., "company_overview", "value_proposition", "competitive_landscape", "objection_responses"
- `prompt_section` — named section for prompt assembly
- `prompt` — the actual content injected into AI prompts

---

## 3. Feature Requirements

### 3.1 Dashboard

**Sales Manager Dashboard:**
- Team activity feed (recent sessions, scores, milestones)
- Aggregate metrics: total sessions, average scores, score trends over time
- Per-rep performance breakdown with sortable columns
- Top performers leaderboard
- Prospect usage distribution (which personas get practiced most)
- Product coverage heatmap (which products reps are practicing)
- Score distribution charts by category
- Quick-access cards to manage prospects, products, rubrics

**Sales Rep Dashboard:**
- Personal session history (recent 10 with quick-view scores)
- Personal performance trend chart (scores over last 30/60/90 days)
- Skill radar chart showing category scores
- Practice streak/frequency tracker
- Suggested practice areas based on lowest-scoring categories
- Quick-launch buttons to start a new practice session
- Leaderboard position within org

### 3.2 Prospect Management (Manager Only)

**List View:**
- Card grid or table view of all prospects in the org
- Filter by industry, personality type, difficulty level
- Search by name, company, role
- Sort by name, created date, usage count

**Create/Edit Prospect:**
- Form with all prospect fields organized in logical sections
- Personality configuration panel (personality, talkativeness, communication style)
- Company context panel (industry, size, current solution, tools, recent events)
- Sub-agent management (add/edit/remove prospect contact personas)
- Image upload or URL for prospect avatar
- Preview of how the prospect description will appear to reps
- Duplicate prospect functionality for creating variations

### 3.3 Product Management (Manager Only)

**List View:**
- Card grid with product name, category, and description preview
- Filter by category
- Search by name

**Create/Edit Product:**
- Product name and category
- Rich text prompt field for detailed product description
- Knowledge base URL/reference field
- Guidelines for what reps should know and emphasize

### 3.4 Practice Session Flow

**Session Setup (Sales Rep):**
1. Rep selects a prospect from available prospects (card selection UI)
2. Rep selects a product to sell (card selection UI)
3. Rep selects call type (cold call, discovery, demo, objection handling, closing, renewal)
4. System displays session configuration summary
5. Rep clicks "Start Practice Call" to initiate Retell AI session

**During Session:**
- Retell AI web SDK handles voice call in-browser
- Real-time call timer displayed
- Minimal UI during call (prospect info card, product reference, end call button)
- Call transcript streams in real-time if supported by Retell

**Post-Session:**
- Session auto-saved with transcript and metadata
- AI-generated scorecard displayed with category-by-category breakdown
- Overall quality score prominently shown
- Specific feedback on strengths and improvement areas
- Key moments highlighted in transcript (objections handled, questions asked, etc.)
- Option to replay/review transcript
- Suggested areas for next practice session

### 3.5 Session History & Review

**Rep View:**
- Chronological list of own sessions with date, prospect, product, score, duration
- Filter by prospect, product, call type, date range, score range
- Click into any session to see full transcript and scorecard
- Score trend visualization

**Manager View:**
- All sessions across the team
- Filter by rep, prospect, product, call type, date range, score range
- Bulk review capabilities
- Add manager notes/annotations to sessions
- Flag sessions for follow-up coaching

### 3.6 Scoring & Rubric Management (Manager Only)

**Scoring Categories:**
- CRUD for evaluation categories
- Set category weight/importance for overall score calculation
- Reorder categories by priority

**Scoring Rubrics:**
- Define what each score level means per category
- Example: "Objection Handling: 5 = Acknowledged concern, asked clarifying question, reframed with value, confirmed resolution"
- Rubric content feeds into AI scoring prompts for consistent evaluation

### 3.7 Agent Configuration (Manager Only)

**Retell AI Agents:**
- List/create/edit voice agents
- Configure voice selection, LLM model
- Manage agent-level system prompts
- Test agent configuration with quick calls

**Model Prompts (`model_prompts`):**
- Named prompt templates that compose the AI's behavior
- Variables/placeholders for prospect, product, and business context injection

**Model Settings (`model_settings`):**
- LLM parameters (temperature, max tokens, etc.)

**Model Output Settings (`model_output_settings`):**
- Response format configuration (JSON structure for scorecards, etc.)

### 3.8 Business Context Management (Manager Only)
- CRUD for organization-level prompt sections
- Sections feed into all AI agent prompts for the org
- Common sections: company overview, value propositions, competitive positioning, common objections & responses, sales methodology guidelines

### 3.9 Team & Organization Management (Manager Only)
- View org members list with roles
- Invite new members (generate invite link or send email)
- Change member roles (user ↔ admin)
- Remove members
- Edit organization name and description

### 3.10 Notifications
- In-app notification bell with unread count
- Notification types: session scored, manager feedback added, new prospect available, practice assignment, milestone achieved
- Mark as read functionality
- Notification preferences in user settings

### 3.11 Settings
- **User Settings:** Profile info, password change, notification preferences, timezone
- **Org Settings (Manager):** Organization name, default scoring rubric, Retell API configuration
- **System Settings (Super Admin):** Global API keys, system defaults, feature flags

---

## 4. Technology Stack

### Backend
- **PHP 8.2+** with traditional LAMP stack architecture
- **MariaDB 10.11+** with PDO and prepared statements exclusively
- **Apache 2.4+** with mod_rewrite enabled

### Frontend
- **HTMX 2.0.8** for all dynamic interactions (no React/Vue/Angular)
- **Bootstrap 5.3.3** with custom design system (CSS variables)
- **Bootstrap Icons 1.11+** for iconography
- **Alpine.js 3.x** (optional) for client-side reactivity where needed
- **jQuery** for utility functions and Retell SDK integration

### External Services
- **Retell AI** — Voice agent platform for practice calls (Web SDK for browser-based calling)
- Retell Web Call SDK loaded via CDN or npm bundle

### Refer to `tech-stack.md` for complete technology details, CDN links, configuration, and architecture patterns.

---

## 5. Project Structure

```
/var/www/html/                     # Web root (publicly accessible)
├── index.php                      # Landing/redirect page
├── login.php                      # Authentication page
├── register.php                   # User registration
├── logout.php                     # Logout handler
├── app.php                        # Main SPA entry point (protected)
├── partials/                      # HTMX endpoint targets
│   ├── auth/                      # Authentication handlers
│   │   ├── login.php
│   │   ├── register.php
│   │   └── forgot-password.php
│   ├── dashboard/                 # Dashboard components
│   │   ├── manager.php            # Manager dashboard view
│   │   ├── rep.php                # Rep dashboard view
│   │   └── widgets/               # Dashboard widget partials
│   │       ├── recent-sessions.php
│   │       ├── score-trends.php
│   │       ├── leaderboard.php
│   │       └── practice-stats.php
│   ├── prospects/                 # Prospect management
│   │   ├── list.php
│   │   ├── form.php
│   │   ├── view.php
│   │   └── agents.php             # Prospect sub-agent management
│   ├── products/                  # Product management
│   │   ├── list.php
│   │   ├── form.php
│   │   └── view.php
│   ├── sessions/                  # Practice sessions
│   │   ├── setup.php              # Session configuration wizard
│   │   ├── call.php               # Active call UI
│   │   ├── review.php             # Post-session scorecard
│   │   ├── list.php               # Session history
│   │   └── detail.php             # Full session detail view
│   ├── scoring/                   # Scoring management
│   │   ├── categories.php
│   │   └── rubrics.php
│   ├── agents/                    # Retell AI agent management
│   │   ├── list.php
│   │   ├── form.php
│   │   └── prompts.php
│   ├── team/                      # Team management
│   │   ├── members.php
│   │   └── invite.php
│   ├── settings/                  # Settings
│   │   ├── profile.php
│   │   ├── organization.php
│   │   └── notifications.php
│   ├── analytics/                 # Analytics & reporting
│   │   ├── team.php
│   │   └── individual.php
│   ├── context/                   # Business context management
│   │   ├── list.php
│   │   └── form.php
│   ├── notifications/             # Notification partials
│   │   ├── list.php
│   │   └── mark-read.php
│   └── components/                # Reusable UI components
│       ├── sidebar.php
│       ├── navbar.php
│       ├── alerts.php
│       └── modals.php
├── assets/
│   ├── css/
│   │   ├── custom.css             # Custom design system
│   │   └── bootstrap.min.css      # Bootstrap 5.3 (or CDN)
│   ├── js/
│   │   ├── custom.js              # App-level JavaScript
│   │   ├── retell-integration.js  # Retell Web SDK wrapper
│   │   └── charts.js              # Chart.js dashboard charts
│   └── images/
│       ├── logo.png
│       └── default-avatar.png
└── uploads/                       # User-uploaded files

/var/www/                          # Above web root (secure)
├── config/
│   ├── database.php               # Database configuration
│   └── config.php                 # Application settings (APP_NAME, Retell API keys, etc.)
├── models/
│   ├── Database.php               # Database singleton
│   ├── User.php                   # User model
│   ├── Prospect.php               # Prospect model
│   ├── Product.php                # Product model
│   ├── Agent.php                  # Agent model
│   ├── Session.php                # Practice session model
│   ├── ScoringCategory.php        # Scoring category model
│   ├── ScoringRubric.php          # Scoring rubric model
│   ├── Notification.php           # Notification model
│   ├── Org.php                    # Organization model
│   └── BusinessContext.php        # Business context model
├── helpers/
│   ├── functions.php              # General utilities
│   ├── validation.php             # Input validation
│   ├── auth.php                   # Authentication helpers
│   ├── csrf.php                   # CSRF protection
│   └── retell.php                 # Retell AI API helper functions
└── views/
    └── layouts/
        ├── main.php               # Main SPA layout
        └── auth.php               # Authentication layout
```

---

## 6. Architecture Requirements

### Partials Pattern
- All partials are dual-purpose: handle GET (render HTML) and POST (process data, return HTML)
- Each partial starts with `session_start()` and auth check
- Protected partials verify user role before rendering manager-only content
- Partials return HTML fragments for HTMX swap, never full pages

### HTMX SPA Behavior
- Single URL `/app` for all authenticated navigation
- All sidebar/nav links use `hx-get` targeting `#page-content`
- Use `hx-push-url="/app"` to keep browser URL clean
- Loading indicators via `htmx-indicator` class on all HTMX requests
- Use `HX-Trigger` response header for toast notifications and event-driven updates

### Role-Based Access Control
- Partials check `$_SESSION['user']['role']` before rendering
- Manager-only partials return 403 HTML fragment for non-admin users
- Sidebar navigation dynamically shows/hides menu items based on role
- API-style partials validate role before processing POST actions

### Retell AI Integration
- Retell Web Call SDK loaded in the call UI partial
- JavaScript wrapper in `retell-integration.js` handles:
  - Initializing Retell client with API key
  - Creating web calls with dynamic agent configuration
  - Streaming transcript events to UI
  - Handling call end events and triggering session save
- Server-side `helpers/retell.php` handles:
  - Creating Retell agents via API
  - Updating agent prompts with prospect/product/business context
  - Retrieving call recordings and transcripts post-call
  - Triggering AI scoring of completed sessions

---

## 7. Database Schema

### Existing Tables (from template, retain as-is)
- `users` — user accounts with org_id, role (user/admin/super_admin)
- `orgs` — organizations/tenants
- `org_members` — org membership with role
- `password_resets` — password reset tokens
- `sessions` — PHP session storage
- `settings` — global system settings
- `user_settings` — per-user settings
- `activities` — activity log
- `notifications` — in-app notifications
- `saved_filters` — saved search/filter configurations

### Application Tables (sales coach specific)
- `prospects` — AI buyer persona companies
- `prospect_agents` — sub-personas within a prospect
- `products` — items being sold
- `agents` — Retell AI voice agent configurations
- `user_agent_session` — practice session records with transcripts and scores
- `scoring_categories` — evaluation dimensions
- `scoring_rubric` — detailed scoring criteria
- `business_context` — org-level prompt sections
- `model_prompts` — named AI prompt templates
- `model_settings` — LLM parameter configuration
- `model_output_settings` — AI output format settings

### Tables to Remove/Ignore (from template, not needed)
- `api_keys` — not needed for this application
- `events` / `event_attendees` — not needed
- `tasks` — not needed
- `webhook_deliveries` — not needed
- `research_jobs` — not needed (referenced by foreign keys in user_agent_session, needs cleanup)

### Schema Enhancements Needed
1. **`user_agent_session`**: Add `user_id` column (currently missing — critical for tracking which rep did the session), add `org_id` for tenant isolation, add `scored_at` timestamp, add `manager_notes` text field, add `scoring_data` JSON field for category-level scores
2. **`scoring_categories`**: Add `org_id` for tenant isolation, add `weight` decimal for weighted scoring, add `sort_order` int for display ordering
3. **`scoring_rubric`**: Add `org_id`, add `category_id` FK to `scoring_categories`, add `score_level` int (1-10), add `criteria` text describing what each level means
4. **`prospects`**: Add `difficulty_level` enum (beginner/intermediate/advanced), add `call_types` JSON (which call types this prospect is suitable for), add `usage_count` int for tracking popularity
5. **`products`**: Add `features_json` JSON field for structured feature list, add `pricing_info` text, add `competitive_notes` text
6. **Remove** `job_id` FK from `user_agent_session` (references removed `research_jobs` table)

---

## 8. UI/UX Design Requirements

### Design System
- Follow all CSS variable conventions from the template design system
- Cards with box-shadow (no borders), hover effects with translateY(-2px)
- Fixed sidebar (210px width) with gradient navbar
- Consistent border-radius, smooth transitions (0.3s ease)
- See `tech-stack.md` for complete design system specification

### Key UI Components

**Prospect Cards:** Visual cards showing prospect avatar, name, role, company, personality badge, industry tag, difficulty indicator. Click to view details or start session.

**Session Setup Wizard:** Step-by-step flow — Select Prospect → Select Product → Configure Call Type → Review & Start. Use HTMX to swap steps within a container.

**Active Call Screen:** Full-width centered UI with prospect avatar/info, real-time timer, waveform/audio indicator, end call button. Minimal distractions.

**Scorecard Display:** Overall score prominently displayed with color coding (green >7, yellow 5-7, red <5). Category breakdown in horizontal bar chart or radar chart. Strengths and improvements listed below. Transcript with highlighted key moments.

**Leaderboard Widget:** Ranked list of reps by average score or total sessions. Profile avatars, rank badges, score displays. Filterable by time period.

**Analytics Charts:** Use Chart.js for score trends (line chart), category breakdown (radar chart), session frequency (bar chart), score distribution (histogram).

### Responsive Design
- Mobile-friendly layout with collapsible sidebar
- Practice session setup works on tablet+
- Active call UI works on any screen size
- Dashboard widgets stack vertically on mobile

---

## 9. Security Requirements

All security requirements from the template apply. Additionally:

- Retell AI API keys stored in config files above web root, never exposed to client
- Retell Web SDK authentication tokens generated server-side and passed to client
- Session transcripts stored securely, accessible only to the session owner and org admins
- Role-based access enforced on every partial (not just UI hiding)
- CSRF tokens on all forms including session setup
- Rate limiting on practice session creation (prevent abuse)
- Input validation on all prospect/product/rubric fields
- XSS prevention on transcript display (transcripts may contain arbitrary text)

---

## 10. Development Phases

### Phase 1: Foundation
- Adapt template auth system with role-based access (admin/user)
- Implement org-based data isolation
- Build sidebar navigation with role-based menu items
- Create basic dashboard shells for manager and rep views
- Database schema migrations (add missing columns, remove unused tables)

### Phase 2: Content Management
- Prospect CRUD (list, create, edit, delete) with all fields
- Prospect agent sub-persona management
- Product CRUD with prompt/knowledge base fields
- Business context management
- Scoring categories and rubrics CRUD

### Phase 3: Agent Configuration
- Retell AI agent CRUD
- Model prompt template management
- Model settings and output configuration
- Agent prompt assembly (combining prospect + product + business context + rubric into system prompt)

### Phase 4: Practice Sessions
- Session setup wizard UI
- Retell Web SDK integration for browser-based voice calls
- Real-time call UI with timer and controls
- Post-call transcript retrieval and storage
- AI-powered session scoring using configured rubrics
- Session review/scorecard display

### Phase 5: Analytics & Polish
- Rep performance dashboard with charts
- Manager team analytics dashboard
- Leaderboard implementation
- Session history with filtering and search
- Notification system integration
- Manager session review and annotation tools

### Phase 6: Enhancements
- Practice modules/assignments (manager assigns specific scenarios to reps)
- Skill gap analysis (AI identifies weak areas across sessions)
- Session comparison (compare two sessions side-by-side)
- Export capabilities (PDF scorecards, CSV session data)
- Gamification (badges, streaks, milestones)

---

## 11. Performance Requirements

- Dashboard loads in under 2 seconds
- Session history pagination (20 per page) with debounced search (500ms)
- Transcript rendering handles large text (10K+ words) without lag
- Chart rendering uses lazy loading
- Database queries use proper indexes (all FK columns, filter columns indexed)
- Retell API calls handled asynchronously where possible

---

## 12. Documentation Requirements

- `README.md` — setup instructions including Retell AI account setup
- `requirements.md` — this file
- `tech-stack.md` — technology details (existing)
- `CLAUDE.md` — AI development guidance for Claude Code
- `docs/activity.md` — development log
- `docs/database.md` — schema documentation
- `docs/retell-integration.md` — Retell AI integration guide
- `docs/api-endpoints.md` — partial endpoint documentation

---

## 13. Constraints

- Must use traditional LAMP stack (no Node.js, no modern JS frameworks)
- Must use HTMX for dynamic interactions (no React, Vue, etc.)
- Must follow directory structure exactly as specified
- Must not modify files in `design/` directory
- Must keep all changes simple and minimal
- Must document all changes in `docs/activity.md`
- Retell AI is the only supported voice agent platform
- All data must be org-scoped (multi-tenant isolation)

---

## 14. Success Criteria

1. ✅ Manager can create and configure prospect personas with personality traits
2. ✅ Manager can create products with detailed descriptions for AI context
3. ✅ Manager can define scoring rubrics aligned to their sales methodology
4. ✅ Rep can browse prospects and products, then initiate a voice practice call
5. ✅ Voice call runs in-browser via Retell AI Web SDK
6. ✅ Post-call transcript is captured and stored
7. ✅ AI generates a scorecard based on configured rubric categories
8. ✅ Rep can view personal session history and performance trends
9. ✅ Manager can view team-wide analytics and individual rep performance
10. ✅ Leaderboard ranks reps by performance within the organization
11. ✅ All data is properly isolated by organization
12. ✅ Role-based access control works correctly across all partials
13. ✅ Application is responsive and works on mobile/tablet
14. ✅ Security measures are in place (CSRF, XSS, SQL injection prevention)

---

## 15. Deliverables

1. Fully functional SalesCoach Pro application
2. Complete documentation set
3. Git repository with clean history
4. Working authentication with role-based access
5. Prospect and product management system
6. Retell AI voice integration for practice sessions
7. AI-powered scoring and feedback system
8. Analytics dashboards for reps and managers
9. Database migration scripts from template schema
10. Design system implemented per template specifications
