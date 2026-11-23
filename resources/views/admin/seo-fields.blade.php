<fieldset class="border rounded-3 p-3 my-3">
    <legend class="float-none w-auto px-2 fs-6">Search engine details</legend>
    <p class="text-secondary small">Optional. Leave blank to use the name and content. Keywords are optional; Google does
        not use this meta tag for ranking.</p><label class="d-block mb-3">SEO title<input class="form-control"
            name="seo_title" maxlength="150" value="{{ $seoRecord?->seo_title }}"><small>Aim for a clear, unique title
            around 50–60 characters.</small></label><label class="d-block mb-3">Meta description
        <textarea class="form-control" name="seo_description" maxlength="320" rows="3">{{ $seoRecord?->seo_description }}</textarea><small>Summarise this page naturally; around 150–160 characters is a useful
            guide.</small>
    </label><label class="d-block">Meta keywords<input class="form-control" name="seo_keywords" maxlength="500"
            value="{{ $seoRecord?->seo_keywords }}" placeholder="Optional, comma separated"></label>
</fieldset>