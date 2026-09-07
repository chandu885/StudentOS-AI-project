# Retrieval-Augmented Generation (RAG) Architecture

## 1. Concept & Problem Statement
Students frequently upload textbooks, lecture slides, research papers, and class notes in PDF format. Traditional LLMs are unaware of custom course-specific notes and may hallucinate. The StudentOS AI RAG pipeline grounds answers strictly in verified course materials.

---

## 2. Ingestion & Indexing Pipeline

```
+------------------+     +----------------------+     +-----------------------+
|  PDF / Document  | --> | Text Extraction &    | --> | Chunking Engine       |
|  Upload (.pdf)   |     | Normalization        |     | (300-500 words,       |
+------------------+     +----------------------+     | 50-word overlap)      |
                                                      +-----------------------+
                                                                  |
                                                                  v
+------------------+     +----------------------+     +-----------------------+
| Inverted Index   | <-- | MySQL Storage        | <-- | Chunk Metadata        |
| & BM25 Scoring   |     | (`document_chunks`)  |     | (page_no, doc_id)     |
+------------------+     +----------------------+     +-----------------------+
```

### 2.1 File Upload & Text Extraction
- Documents uploaded via `frontend/student/pdf-qa.php` are staged in `storage/documents/`.
- Backend extracts text streams, stripping out non-printable ASCII artifacts, page headers, and trailing linebreaks.

### 2.2 Semantic Chunking
- Documents are split into semantic chunks of roughly 300–500 words with a 50-word sliding window overlap.
- Each chunk preserves metadata: `document_id`, `chunk_index`, `page_number`, and content length.
- Stored persistently in `document_chunks` table.

---

## 3. Query Retrieval & Context Augmentation

```
                     User Query: "What is Dijkstra's algorithm?"
                                        |
                                        v
                            +-----------------------+
                            | Retrieval Engine      |
                            | (Keyword & BM25 Match)|
                            +-----------------------+
                                        |
                                        v
                                Top-K Rel Chunks
                                        |
                                        v
+-------------------------------------------------------------------------------+
| System Prompt:                                                                |
| "You are an academic tutor. Ground your answer ONLY in the context below:     |
|  --- CONTEXT START ---                                                        |
|  [Page 42]: Dijkstra's algorithm calculates shortest path in graphs...        |
|  --- CONTEXT END ---                                                          |
|  Question: What is Dijkstra's algorithm?"                                     |
+-------------------------------------------------------------------------------+
                                        |
                                        v
                                 Google Gemini API
                                        |
                                        v
                            Verified Factual Answer
```

---

## 4. Citation & Verifiability
- Every answer produced by the RAG pipeline includes bracketed source references (e.g., `[Document: CS301_Notes.pdf, Page: 14]`).
- If the uploaded document does not contain sufficient information to address the query, the engine explicitly alerts the student rather than hallucinating external facts.
