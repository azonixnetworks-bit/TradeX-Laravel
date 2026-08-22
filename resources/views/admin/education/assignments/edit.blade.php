{{-- TradeX Admin — ADMIN-03 Edit Assignment --}}

@extends('admin.layouts.app')

@section('title', 'Edit ' . $assignment->title . ' | TradeX Education')
@section('page-title', 'Education')

@push('styles')
<style>
    .form-wrap{max-width:1050px;margin:0 auto}.context{padding:18px 21px;margin-bottom:20px}.context h2{margin:5px 0;font-size:18px}.context p{margin:0;opacity:.58;font-size:12px}
    .form-panel{padding:24px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.field.full{grid-column:1/-1}.field label{display:block;margin-bottom:7px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;opacity:.7}
    .field input,.field textarea,.field select{width:100%;box-sizing:border-box;border:1px solid rgba(255,255,255,.09);border-radius:10px;background:rgba(255,255,255,.025);color:inherit;padding:11px 12px;font:inherit;outline:none}.field select option{color:#111}.field textarea{min-height:110px;resize:vertical}.field textarea.long{min-height:190px}
    .help{display:block;margin-top:6px;font-size:10px;opacity:.48;line-height:1.45}.notice{grid-column:1/-1;padding:13px 14px;border:1px solid rgba(218,178,88,.18);border-radius:11px;background:rgba(218,178,88,.05);font-size:11px;line-height:1.6;opacity:.82}
    .upload-box{grid-column:1/-1;padding:18px;border:1px dashed rgba(218,178,88,.32);border-radius:13px;background:rgba(218,178,88,.035)}.upload-box>strong{display:block;margin-bottom:6px;color:#e0ba65;font-size:13px}.upload-box p{margin:0 0 12px;font-size:11px;opacity:.58;line-height:1.55}
    .material-current{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:center;margin-bottom:14px;padding:13px 14px;border:1px solid rgba(121,221,165,.18);border-radius:11px;background:rgba(121,221,165,.045)}.material-current strong{margin:0;color:#8fe1b2}.material-current span{display:block;margin-top:4px;font-size:10px;opacity:.55}.material-size{font-size:11px;font-weight:800;white-space:nowrap}
    .check{display:flex;gap:9px;align-items:flex-start;padding:12px;border:1px solid rgba(255,255,255,.07);border-radius:10px}.check input{width:auto;margin-top:2px}.remove{margin-top:12px;border-color:rgba(255,91,91,.16);background:rgba(255,91,91,.025)}
    .actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px;padding-top:19px;border-top:1px solid rgba(255,255,255,.06)}.errors{margin-bottom:17px;padding:13px 15px;border:1px solid rgba(255,91,91,.22);border-radius:10px;background:rgba(255,91,91,.05)}.errors ul{margin:7px 0 0;padding-left:18px;font-size:11px}
    @media(max-width:760px){.grid{grid-template-columns:1fr}.field.full,.notice,.upload-box{grid-column:auto}.material-current{grid-template-columns:1fr}.actions{flex-direction:column-reverse}.actions a,.actions button{width:100%;box-sizing:border-box;text-align:center}}
</style>
@endpush

@section('content')

<div class="page-header">
    <div>
        <div class="page-eyebrow">EDUCATION MANAGEMENT</div>
        <h1>Edit <span>Assignment</span></h1>
        <p>Update assignment content and replace or remove the admin-provided material.</p>
    </div>

    <div>
        <a
            class="secondary-btn"
            href="{{ route('admin.education.courses.modules.lessons.assignments.index', ['course'=>$course,'module'=>$module,'lesson'=>$lesson]) }}"
        >
            ← Back to Assignments
        </a>
    </div>
</div>

<div class="form-wrap">

    <section class="panel context">
        <div class="panel-eyebrow">
            {{ $lesson->code }} · {{ $assignment->status }}
        </div>

        <h2>{{ $assignment->title }}</h2>
        <p>Submission method: {{ $assignment->submission_method }}</p>
    </section>

    @if($errors->any())
        <div class="errors">
            <strong>Please correct the following fields.</strong>

            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        enctype="multipart/form-data"
        action="{{ route('admin.education.courses.modules.lessons.assignments.update', ['course'=>$course,'module'=>$module,'lesson'=>$lesson,'assignment'=>$assignment]) }}"
    >
        @csrf
        @method('PATCH')

        <section class="panel form-panel">

            <div class="panel-header">
                <div>
                    <div class="panel-eyebrow">ASSIGNMENT POLICY</div>
                    <h2>Assignment Details</h2>
                </div>
            </div>

            <div class="grid">

                <div class="notice">
                    Admin assignment/question material can be uploaded and replaced here.
                    Student answer files remain external to TradeX and continue through the approved WhatsApp workflow.
                </div>

                <div class="field full">
                    <label>Title</label>
                    <input name="title" maxlength="200" required value="{{ old('title',$assignment->title) }}">
                </div>

                <div class="field full">
                    <label>Heading</label>
                    <input name="heading" maxlength="250" value="{{ old('heading',$assignment->heading) }}">
                </div>

                <div class="field full">
                    <label>Question / Description</label>
                    <textarea class="long" name="description">{{ old('description',$assignment->description) }}</textarea>
                </div>

                <div class="upload-box">
                    <strong>Admin Assignment Material</strong>

                    @if($assignment->hasMaterial())
                        <div class="material-current">
                            <div>
                                <strong>{{ $assignment->materialDisplayName() }}</strong>

                                <span>
                                    {{ $assignment->material_mime_type ?: 'Unknown MIME' }}
                                    @if($assignment->material_uploaded_at)
                                        · Uploaded {{ $assignment->material_uploaded_at->format('d M Y H:i') }}
                                    @endif
                                </span>
                            </div>

                            <div class="material-size">
                                @if($assignment->material_size_bytes !== null)
                                    {{ number_format($assignment->material_size_bytes / 1024 / 1024, 2) }} MB
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    @else
                        <p>No protected admin material is currently attached.</p>
                    @endif

                    <p>
                        Uploading a new file replaces the current material after the database update succeeds.
                        Supported: PDF, JPG, JPEG, PNG, WEBP, DOCX, XLSX, PPTX and TXT. Maximum 25 MB.
                    </p>

                    <input
                        type="file"
                        name="material_file"
                        accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx,.pptx,.txt"
                    >

                    @if($assignment->hasMaterial())
                        <label class="check remove">
                            <input type="hidden" name="remove_material" value="0">

                            <input
                                type="checkbox"
                                name="remove_material"
                                value="1"
                                @checked((bool) old('remove_material', false))
                            >

                            <span>
                                Remove the current admin material without uploading a replacement.
                                Do not select this together with a replacement file.
                            </span>
                        </label>
                    @endif
                </div>

                <div class="field">
                    <label>Legacy Reference Image Path</label>

                    <input
                        name="reference_image_path"
                        maxlength="500"
                        value="{{ old('reference_image_path',$assignment->reference_image_path) }}"
                    >

                    <span class="help">Backward-compatible private logical path only.</span>
                </div>

                <div class="field">
                    <label>Display Order</label>

                    <input
                        type="number"
                        min="0"
                        max="4294967295"
                        name="sort_order"
                        value="{{ old('sort_order',$assignment->sort_order) }}"
                    >
                </div>

                <div class="field">
                    <label>Submission Method</label>

                    <select name="submission_method" required>
                        <option
                            value="WHATSAPP"
                            @selected(old('submission_method',$assignment->submission_method)==='WHATSAPP')
                        >
                            WHATSAPP
                        </option>

                        <option
                            value="NONE"
                            @selected(old('submission_method',$assignment->submission_method)==='NONE')
                        >
                            NONE
                        </option>
                    </select>
                </div>

                <div class="field">
                    <label for="assignment-is-required">Required</label>

                    <div class="check">
                        <input type="hidden" name="is_required" value="0">

                        <input
                            id="assignment-is-required"
                            type="checkbox"
                            name="is_required"
                            value="1"
                            @checked((bool)old('is_required',$assignment->is_required))
                        >

                        <span>Mark this assignment as required.</span>
                    </div>
                </div>

                <div class="field full">
                    <label>Student Submission Instructions</label>

                    <textarea name="submission_instructions">{{ old('submission_instructions',$assignment->submission_instructions) }}</textarea>

                    <span class="help">
                        Student answer submission stays external through WhatsApp when selected.
                    </span>
                </div>
            </div>

            <div class="actions">
                <a
                    class="secondary-btn"
                    href="{{ route('admin.education.courses.modules.lessons.assignments.index', ['course'=>$course,'module'=>$module,'lesson'=>$lesson]) }}"
                >
                    Cancel
                </a>

                <button class="primary-btn" type="submit">
                    Save Assignment
                </button>
            </div>
        </section>
    </form>
</div>

@endsection
