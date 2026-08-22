{{-- TradeX Admin — ADMIN-03 Create Assignment --}}

@extends('admin.layouts.app')

@section('title', 'Create Assignment | TradeX Education')
@section('page-title', 'Education')

@push('styles')
<style>
    .form-wrap{max-width:1050px;margin:0 auto}.context{padding:18px 21px;margin-bottom:20px}.context h2{margin:5px 0;font-size:18px}.context p{margin:0;opacity:.58;font-size:12px}
    .form-panel{padding:24px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.field.full{grid-column:1/-1}.field label{display:block;margin-bottom:7px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;opacity:.7}
    .field input,.field textarea,.field select{width:100%;box-sizing:border-box;border:1px solid rgba(255,255,255,.09);border-radius:10px;background:rgba(255,255,255,.025);color:inherit;padding:11px 12px;font:inherit;outline:none}.field select option{color:#111}.field textarea{min-height:110px;resize:vertical}.field textarea.long{min-height:190px}
    .help{display:block;margin-top:6px;font-size:10px;opacity:.48;line-height:1.45}.notice{grid-column:1/-1;padding:13px 14px;border:1px solid rgba(218,178,88,.18);border-radius:11px;background:rgba(218,178,88,.05);font-size:11px;line-height:1.6;opacity:.82}
    .upload-box{grid-column:1/-1;padding:18px;border:1px dashed rgba(218,178,88,.32);border-radius:13px;background:rgba(218,178,88,.035)}.upload-box strong{display:block;margin-bottom:6px;color:#e0ba65;font-size:13px}.upload-box p{margin:0 0 12px;font-size:11px;opacity:.58;line-height:1.55}.upload-box input[type=file]{background:rgba(255,255,255,.018)}
    .check{display:flex;gap:9px;align-items:flex-start;padding:12px;border:1px solid rgba(255,255,255,.07);border-radius:10px}.check input{width:auto;margin-top:2px}.actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px;padding-top:19px;border-top:1px solid rgba(255,255,255,.06)}.errors{margin-bottom:17px;padding:13px 15px;border:1px solid rgba(255,91,91,.22);border-radius:10px;background:rgba(255,91,91,.05)}.errors ul{margin:7px 0 0;padding-left:18px;font-size:11px}
    @media(max-width:760px){.grid{grid-template-columns:1fr}.field.full,.notice,.upload-box{grid-column:auto}.actions{flex-direction:column-reverse}.actions a,.actions button{width:100%;box-sizing:border-box;text-align:center}}
</style>
@endpush

@section('content')

<div class="page-header">
    <div>
        <div class="page-eyebrow">EDUCATION MANAGEMENT</div>
        <h1>Create <span>Assignment</span></h1>
        <p>Create the student task and optionally upload admin-provided question/reference material.</p>
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
            {{ $module->code }} · {{ $lesson->code }}
        </div>

        <h2>{{ $lesson->title }}</h2>

        <p>
            {{ $lesson->short_description ?: 'No lesson summary has been added.' }}
        </p>
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
        action="{{ route('admin.education.courses.modules.lessons.assignments.store', ['course'=>$course,'module'=>$module,'lesson'=>$lesson]) }}"
    >
        @csrf

        <section class="panel form-panel">

            <div class="panel-header">
                <div>
                    <div class="panel-eyebrow">ASSIGNMENT POLICY</div>
                    <h2>Assignment Details</h2>
                </div>
            </div>

            <div class="grid">

                <div class="notice">
                    <strong>Admin material upload is allowed.</strong>
                    Upload the question paper, reference image, PDF or supporting document for the student.
                    Student answer uploads are still not accepted inside TradeX; student submission remains external through WhatsApp when enabled.
                </div>

                <div class="field full">
                    <label>Title</label>
                    <input name="title" maxlength="200" required value="{{ old('title') }}">
                </div>

                <div class="field full">
                    <label>Heading</label>
                    <input name="heading" maxlength="250" value="{{ old('heading') }}">
                </div>

                <div class="field full">
                    <label>Question / Description</label>
                    <textarea class="long" name="description">{{ old('description') }}</textarea>
                </div>

                <div class="upload-box">
                    <strong>Admin Assignment Material</strong>

                    <p>
                        Optional protected upload. Supported: PDF, JPG, JPEG, PNG, WEBP, DOCX, XLSX, PPTX and TXT.
                        Maximum size: 25 MB. This is the assignment/question material FROM the admin, not the student's answer.
                    </p>

                    <input
                        type="file"
                        name="material_file"
                        accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx,.pptx,.txt"
                    >
                </div>

                <div class="field">
                    <label>Legacy Reference Image Path</label>

                    <input
                        name="reference_image_path"
                        maxlength="500"
                        value="{{ old('reference_image_path') }}"
                    >

                    <span class="help">
                        Optional backward-compatible private logical path. Normal workflow should use Admin Assignment Material above.
                    </span>
                </div>

                <div class="field">
                    <label>Display Order</label>

                    <input
                        type="number"
                        min="0"
                        max="4294967295"
                        name="sort_order"
                        value="{{ old('sort_order',0) }}"
                    >
                </div>

                <div class="field">
                    <label>Submission Method</label>

                    <select name="submission_method" required>
                        <option
                            value="WHATSAPP"
                            @selected(old('submission_method','WHATSAPP')==='WHATSAPP')
                        >
                            WHATSAPP
                        </option>

                        <option
                            value="NONE"
                            @selected(old('submission_method')==='NONE')
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
                            @checked((bool)old('is_required',true))
                        >

                        <span>Mark this assignment as required.</span>
                    </div>
                </div>

                <div class="field full">
                    <label>Student Submission Instructions</label>

                    <textarea
                        name="submission_instructions"
                        placeholder="Example: Complete the task and send your answer through the approved TradeX WhatsApp contact."
                    >{{ old('submission_instructions') }}</textarea>

                    <span class="help">
                        This instruction is for the student's external answer submission. It does not enable in-app learner uploads.
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
                    Create Assignment
                </button>
            </div>
        </section>
    </form>
</div>

@endsection
