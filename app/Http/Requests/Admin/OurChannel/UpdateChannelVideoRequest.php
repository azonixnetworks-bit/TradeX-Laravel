<?php

namespace App\Http\Requests\Admin\OurChannel;

use App\Models\AdminUser;
use App\Support\YouTubeVideo;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChannelVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'our_channel.manage'
            );
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:180',
            ],
            'short_description' => [
                'nullable',
                'string',
                'max:500',
            ],
            'description' => [
                'nullable',
                'string',
                'max:20000',
            ],
            'hashtags' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'youtube_url' => [
                'nullable',
                'string',
                'max:2048',
                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {
                    if (
                        $value !== null
                        && trim((string) $value) !== ''
                        && YouTubeVideo::extractVideoId(
                            (string) $value
                        ) === null
                    ) {
                        $fail(
                            'Enter a valid HTTPS YouTube video URL from youtube.com or youtu.be.'
                        );
                    }
                },
            ],
            'language_code' => [
                'required',
                'string',
                'min:2',
                'max:10',
                'regex:/^[A-Za-z]{2,3}(?:-[A-Za-z]{2,4})?$/',
            ],
            'is_featured' => [
                'sometimes',
                'boolean',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:1000000',
            ],
            'status' => ['prohibited'],
            'published_at' => ['prohibited'],
            'publish_requested_at' => ['prohibited'],
            'youtube_video_id' => ['prohibited'],
            'source_disk' => ['prohibited'],
            'source_path' => ['prohibited'],
            'source_sha256' => ['prohibited'],
            'uuid' => ['prohibited'],
            'created_by_admin_id' => ['prohibited'],
            'updated_by_admin_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('language_code')) {
            $this->merge([
                'language_code' => strtolower(
                    trim(
                        (string) $this->input(
                            'language_code'
                        )
                    )
                ),
            ]);
        }

        if ($this->has('youtube_url')) {
            $this->merge([
                'youtube_url' => trim(
                    (string) $this->input(
                        'youtube_url'
                    )
                ),
            ]);
        }
    }
}
