@php
    $message = $notificationMessage ?? null;
@endphp

<div class="form-grid">
    <div class="form-group">
        <label>Audience</label>
        <select
            name="audience_access_level"
            required
        >
            @foreach($audiences as $audience)
                <option
                    value="{{ $audience }}"
                    @selected(
                        old(
                            'audience_access_level',
                            $message?->audience_access_level
                                ?? 'ALL'
                        ) === $audience
                    )
                >
                    {{ $audience }}
                </option>
            @endforeach
        </select>
        @error('audience_access_level')
            <small>{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label>Category</label>
        <select
            name="category"
            required
        >
            @foreach($categories as $category)
                <option
                    value="{{ $category }}"
                    @selected(
                        old(
                            'category',
                            $message?->category
                                ?? 'GENERAL'
                        ) === $category
                    )
                >
                    {{ $category }}
                </option>
            @endforeach
        </select>
        @error('category')
            <small>{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label>Priority</label>
        <select
            name="priority"
            required
        >
            @foreach($priorities as $priority)
                <option
                    value="{{ $priority }}"
                    @selected(
                        old(
                            'priority',
                            $message?->priority
                                ?? 'NORMAL'
                        ) === $priority
                    )
                >
                    {{ $priority }}
                </option>
            @endforeach
        </select>
        @error('priority')
            <small>{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label>Action</label>
        <select
            name="action_type"
            required
        >
            @foreach($actionTypes as $actionType)
                <option
                    value="{{ $actionType }}"
                    @selected(
                        old(
                            'action_type',
                            $message?->action_type
                                ?? 'NONE'
                        ) === $actionType
                    )
                >
                    {{ $actionType }}
                </option>
            @endforeach
        </select>
        @error('action_type')
            <small>{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label>Action Resource UUID</label>
        <input
            type="text"
            name="action_resource_uuid"
            maxlength="36"
            value="{{ old(
                'action_resource_uuid',
                $message?->action_resource_uuid
            ) }}"
            placeholder="Only for course, lesson, trade idea or channel video actions"
        >
        <small>
            No arbitrary URLs are accepted. Resource UUID is required only for resource-backed action types.
        </small>
        @error('action_resource_uuid')
            <small>{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label>Expiry</label>
        <input
            type="datetime-local"
            name="expires_at"
            value="{{ old(
                'expires_at',
                $message?->expires_at
                    ?->format('Y-m-d\TH:i')
            ) }}"
        >
        <small>Optional. Must remain later than publication time.</small>
        @error('expires_at')
            <small>{{ $message }}</small>
        @enderror
    </div>
</div>

<div
    class="form-group"
    style="margin-top:18px;"
>
    <label>Title</label>
    <input
        type="text"
        name="title"
        maxlength="180"
        value="{{ old(
            'title',
            $message?->title
        ) }}"
        required
    >
    @error('title')
        <small>{{ $message }}</small>
    @enderror
</div>

<div
    class="form-group"
    style="margin-top:18px;"
>
    <label>Message</label>
    <textarea
        name="body"
        maxlength="5000"
        rows="10"
        required
    >{{ old(
        'body',
        $message?->body
    ) }}</textarea>
    @error('body')
        <small>{{ $message }}</small>
    @enderror
</div>
