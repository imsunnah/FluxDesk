# ⚡ FluxDesk — Real-Time Support Workspace & Agent Chat OS

FluxDesk is an enterprise-grade, high-concurrency real-time customer support workspace built as a monolithic Single Page Application (SPA). It pairs the robust backend routing and security of **Laravel 11** with the reactive presentation layer of **Vue 3 (Composition API)** using **Inertia.js**.

Powered by **Laravel Reverb**, FluxDesk delivers sub-100ms bi-directional WebSocket messaging, live agent presence tracking, typing indicators, private internal team notes, and queue-backed background notifications in a modern chat UI inspired by WhatsApp, Slack, and Discord.

---

## 🎨 Key Features & Architectural Highlights

### 💬 Modern Chat UI & Live Collaboration

- **Sub-100ms Bi-Directional Messaging:** Low-latency WebSocket broadcasting powered by native Laravel Reverb.
- **Presence Channels & Live Typing:** Real-time visibility into active agents viewing a conversation (`PresenceChannel`) and customer/agent typing indicators (whisper).
- **Internal Team Notes:** Dynamic toggle allowing agents to post private amber-highlighted notes visible only to team members within the message stream.
- **Canned Responses (`/` Shortcuts):** Slash-command template picker to insert pre-written support replies instantly into the chat bar.
- **Optimistic UI & Auto-Scrolling:** Instant client-side message rendering before network confirmation with automated smooth scrolling to the latest message.

### 🏛️ Engineering & Backend Architecture

- **Inertia.js Monolith:** SPA user experience with clean client-side navigation while retaining server-side Laravel routing, authorization, and data validation without REST/GraphQL API overhead.
- **SQLite Storage Layer:** Zero-config, lightweight relational storage model engineered for fast queries and simple environment setup.
- **Asynchronous Queue Pipeline:** Heavy activity logging, conversation lifecycle metrics, and offline email notification fallbacks decoupled using Redis Queues.
- **Filterable Workspace Views:** Instant client-side and server-side inbox filtering across ticket statuses (`open`, `pending`, `resolved`), priority tags (`low`, `medium`, `high`), and agent assignments.

---

## 🏗️ System Architecture & Data Flow

```text
 ┌─────────────────────────────────────────────────────────────────────────┐
 │                      Vue 3 + Inertia.js Monolith Frontend               │
 │  ┌───────────────────────┬──────────────────────┬────────────────────┐  │
 │  │ Chat Sidebar Panel    │ Real-Time Thread UI  │ Customer Inspector │  │
 │  └───────────────────────┴──────────────────────┴────────────────────┘  │
 └─────────────▲───────────────────────────┬───────────────────────────────┘
               │                           │
               │ WebSockets (Reverb)       │ Form Submissions / HTTP
               │                           ▼
 ┌─────────────┴───────────────────────────────────────────────────────────┐
 │                            Laravel 11 Backend API                       │
 │  ┌───────────────────────┬──────────────────────┬────────────────────┐  │
 │  │ Controllers & Routes │ Reverb Broadcasting  │ Redis Queue Jobs   │  │
 │  └───────────────────────┴──────────────────────┴────────────────────┘  │
 └─────────────┬───────────────────────────────────────────────────────────┘
               │
               ▼
 ┌─────────────────────────────────────────────────────────────────────────┐
 │               SQLite Database  |  Redis Pub/Sub Cache Engine            │
 └─────────────────────────────────────────────────────────────────────────┘
```

---

## 🗄️ Database Architecture

- **`conversations`**: Manages customer identity, ticket lifecycle status (`open`, `pending`, `resolved`), priority levels (`low`, `medium`, `high`), assigned support agents, and timestamp tracking.
- **`messages`**: Stores message contents, sender classifications (customer vs. agent), internal note flags, read state markers, and user relations.
- **`canned_responses`**: Manages slash-command shortcuts (`/greeting`, `/pricing`) and pre-configured quick reply snippets.

---

## 🚀 Getting Started (Local Development Setup)

### Prerequisites

- PHP >= 8.2 with `pdo_sqlite` extension enabled
- Composer 2.x
- Node.js >= 18.x & NPM
- Redis (Optional: required for background queue processing)

### Installation Steps

1. **Clone the Repository:**

   ```bash
   git clone https://github.com/your-username/fluxdesk.git
   cd fluxdesk
   ```

2. **Install PHP & Frontend Dependencies:**

   ```bash
   composer install
   npm install
   ```

3. **Configure Environment:**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Initialize SQLite Database & Seed Data:**

   ```bash
   touch database/database.sqlite
   php artisan migrate --seed
   ```

5. **Start WebSocket Server (Laravel Reverb):**

   ```bash
   php artisan reverb:start
   ```

6. **Start Application Server & Asset Compiler:**

   Run these commands concurrently or in separate terminal tabs:

   ```bash
   php artisan serve
   npm run dev
   ```

7. **Access Application:**

   Open your browser and navigate to [http://localhost:8000](http://localhost:8000).

---

## 🧪 Testing

Execute the automated test suite covering feature routes, authorization policies, and WebSocket event assertions:

```bash
php artisan test
```

---

## 📄 License

This project is open-source software licensed under the [MIT License](https://opensource.org/licenses/MIT).
