# Generative AI Integration & Engine

## 1. Overview
StudentOS AI integrates advanced Generative AI capabilities powered by Google Gemini (e.g. `gemini-1.5-flash`) via `backend/services/AIService.php`. The platform empowers students and faculty with academic tutoring, document summarization, automated quiz synthesis, study schedule generation, and weak-area diagnostic recommendations.

---

## 2. Supported AI Capabilities

### 2.1 24/7 Academic Study Assistant (`/api/ai/assistant`)
- Multi-turn conversational interface providing step-by-step concept explanations, math and code debugging, and essay drafting assistance.
- Prompt templates enforce strict pedagogical behavior: encouraging active learning, concise formatting, and verifiable examples.

### 2.2 Dynamic Study Planner (`/api/ai/planner`)
- Analyzes upcoming assignment due dates, scheduled exam dates, and current syllabus completion.
- Synthesizes an optimized day-by-day revision schedule with suggested focus intervals.

### 2.3 Automated Quiz Generator (`/api/ai/quiz`)
- Accepts a subject, difficulty level, and number of questions (or uploaded study text).
- Produces structured JSON objects containing:
  - Question statement
  - 4 multiple choice options (A, B, C, D)
  - Correct answer key
  - Pedagogical explanation for why the answer is correct

### 2.4 Intelligent Text & Note Summarizer (`/api/ai/summarize`)
- Ingests long lecture transcriptions, textbook excerpts, or dense research articles.
- Yields structured executive summaries:
  - Core takeaways
  - Bulleted key concepts
  - Important terminology and formulas
  - Suggested review questions

### 2.5 Personalized Academic Recommendations (`/api/ai/recommendations`)
- Evaluates student exam and quiz performance to flag topics below target mastery.
- Generates curated focus paths and remedial resource links.

---

## 3. Heuristic Fallback & Graceful Degradation
To guarantee zero downtime in environments without an active internet connection or during API quota exhaustion:
- `AIService.php` includes built-in heuristic fallback algorithms.
- When `GEMINI_API_KEY` is omitted or Gemini returns a rate-limit error, the engine seamlessly switches to internal rule-based heuristic templates to ensure uninterrupted frontend user experience.

---

## 4. Configuration
Set your Gemini API key in `.env`:
```env
GEMINI_API_KEY=AIzaSyD...
GEMINI_MODEL=gemini-1.5-flash
ENABLE_RAG=true
```
