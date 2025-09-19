@extends("layouts.auth")

@section("title", "Messages")

@push("styles")
    <style>
        .message-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 1.5rem;
            min-height: 540px;
        }

        .message-sidebar {
            background: rgba(15, 23, 42, 0.6);
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.15);
            overflow: hidden;
        }

        .message-thread-list {
            max-height: 540px;
            overflow-y: auto;
        }

        .message-thread-item {
            cursor: pointer;
            padding: 0.9rem 1.1rem;
            transition: background 0.15s ease;
        }

        .message-thread-item:hover,
        .message-thread-item.active {
            background: rgba(59, 130, 246, 0.08);
        }

        .message-panel {
            background: rgba(15, 23, 42, 0.6);
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.14);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .message-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .bubble {
            max-width: 78%;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            position: relative;
        }

        .bubble.me {
            margin-left: auto;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.9), rgba(14, 165, 233, 0.85));
            color: #0f172a;
        }

        .bubble.them {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(59, 130, 246, 0.15);
        }

        .typing-indicator {
            font-size: 0.85rem;
            color: rgba(96, 165, 250, 0.85);
        }

        .message-highlight {
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.6);
            border-radius: 1rem;
        }

        @media (max-width: 992px) {
            .message-layout {
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .message-sidebar {
                max-height: 260px;
            }
        }
    </style>
@endpush

@section("content")
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">Messages</h1>
            <p class="text-muted-soft mb-0">Stay in sync with your counterpart before and after handover.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-light btn-sm">Back to dashboard</a>
    </div>

    <div class="message-layout" data-messages-app>
        <aside class="message-sidebar">
            <div class="px-3 py-2 border-bottom border-light-subtle">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="include-archived" data-include-archived>
                    <label class="form-check-label small text-muted-soft" for="include-archived">Show archived</label>
                </div>
            </div>
            <div class="px-3 py-3 border-bottom border-light-subtle">
                <input type="search" class="form-control form-control-sm" placeholder="Search conversations" data-thread-search>
            </div>
            <div class="message-thread-list list-group list-group-flush" data-thread-list>
                <div class="text-center text-muted-soft py-4">Loading conversations.</div>
            </div>
        </aside>

        <section class="message-panel">
            <header class="border-bottom border-light-subtle px-4 py-3" data-thread-header>
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <h2 class="h5 mb-1" data-thread-title>Select a conversation</h2>
                        <small class="text-muted-soft" data-thread-subtitle>Messages will appear here.</small>
                        <div class="small text-warning mt-1" data-thread-status></div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-2" data-thread-actions></div>
                </div>
            </header>
            <div class="message-scroll" data-message-scroll>
                <p class="text-muted-soft text-center">No messages yet.</p>
            </div>
            <footer class="border-top border-light-subtle px-4 py-3">
                <form data-message-form class="d-flex gap-2 align-items-end">
                    <textarea class="form-control" rows="2" placeholder="Type your message" data-message-input disabled></textarea>
                    <button type="submit" class="btn btn-primary" data-message-submit disabled>Send</button>
                </form>
                <div class="text-warning small mt-2" data-muted-notice></div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="typing-indicator" data-typing-indicator></div>
                    <small class="text-muted-soft" data-read-indicator></small>
                </div>
            </footer>
        </section>
    </div>
@endsection

@push("scripts")
    @vite("resources/js/messages-page.js")
@endpush
