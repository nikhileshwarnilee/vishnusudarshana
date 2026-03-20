<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Login required');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marquee Editor Frame</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fff;
        }

        .frame-shell {
            padding: 0;
        }

        .editor-wrapper {
            border: 0;
            border-radius: 0;
            overflow: hidden;
            background: #fff;
        }

        .editor-area {
            min-height: 620px;
            width: 100%;
            padding: 14px;
            outline: none;
            font-size: 1em;
            line-height: 1.6;
            box-sizing: border-box;
        }

        .frame-alert {
            display: none;
            margin: 12px 0 0;
            padding: 12px 14px;
            border-radius: 10px;
            background: #fdeaea;
            color: #9b1c1c;
            border: 1px solid #f5c2c7;
            font-size: 0.95em;
        }
    </style>
</head>
<body>
<div class="frame-shell">
    <div class="editor-wrapper">
        <textarea id="bodyEditor" class="editor-area" aria-label="Site marquee editor"></textarea>
    </div>
    <textarea id="body" style="display:none;"></textarea>
    <div id="editorLoadError" class="frame-alert"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.1/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    const bodyTextarea = document.getElementById('body');
    const bodyEditorEl = document.getElementById('bodyEditor');
    const editorLoadError = document.getElementById('editorLoadError');
    let queuedHtml = '';

    const showEditorError = message => {
        if (!editorLoadError) {
            return;
        }
        editorLoadError.textContent = message;
        editorLoadError.style.display = 'block';
    };

    const uploadEditorFile = file => new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'upload-editor-image.php', true);
        xhr.responseType = 'json';
        xhr.onerror = () => reject('Upload failed');
        xhr.onload = () => {
            const response = xhr.response || {};
            if (!response.url) {
                reject(response.error || 'Upload failed');
                return;
            }
            resolve(response.url);
        };
        const data = new FormData();
        data.append('upload', file);
        xhr.send(data);
    });

    const getEditorContent = () => {
        if (window.tinymce && window.tinymce.get('bodyEditor')) {
            return window.tinymce.get('bodyEditor').getContent();
        }
        return bodyEditorEl ? (bodyEditorEl.value || '') : '';
    };

    const notifyParent = () => {
        if (window.parent && window.parent !== window && typeof window.parent.vsMarqueeEditorParentChange === 'function') {
            window.parent.vsMarqueeEditorParentChange(getEditorContent());
        }
    };

    window.vsMarqueeEditorGetContent = getEditorContent;
    window.vsMarqueeEditorSetContent = function (html) {
        queuedHtml = html || '';
        if (bodyTextarea) {
            bodyTextarea.value = queuedHtml;
        }
        if (window.tinymce && window.tinymce.get('bodyEditor')) {
            window.tinymce.get('bodyEditor').setContent(queuedHtml || '');
            notifyParent();
        } else if (bodyEditorEl) {
            bodyEditorEl.value = queuedHtml || '';
        }
    };

    if (bodyEditorEl) {
        bodyEditorEl.value = queuedHtml || '';
    }

    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '#bodyEditor',
            height: 620,
            menubar: 'file edit view insert format tools table help',
            plugins: 'advlist autolink lists link image media table code fullscreen preview searchreplace visualblocks wordcount charmap emoticons codesample autoresize anchor',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table blockquote codesample | removeformat | preview code fullscreen',
            toolbar_mode: 'sliding',
            branding: false,
            promotion: false,
            content_style: 'body { font-family:Segoe UI,Tahoma,Verdana,sans-serif; font-size:16px } img { max-width:100%; height:auto; }',
            object_resizing: 'img,table,iframe,video',
            image_advtab: true,
            image_caption: true,
            image_dimensions: true,
            media_dimensions: true,
            media_live_embeds: true,
            link_default_target: '_blank',
            extended_valid_elements: 'iframe[src|frameborder|style|scrolling|class|width|height|name|align|allow|allowfullscreen],video[*],source[*]',
            setup: function(editor) {
                editor.on('init', function() {
                    editor.setContent(queuedHtml || '');
                    if (bodyTextarea) {
                        bodyTextarea.value = editor.getContent();
                    }
                    notifyParent();
                });
                editor.on('change input undo redo', function() {
                    if (bodyTextarea) {
                        bodyTextarea.value = editor.getContent();
                    }
                    notifyParent();
                });
            },
            images_upload_handler: (blobInfo, progress) => new Promise((resolve, reject) => {
                const file = blobInfo.blob();
                uploadEditorFile(file).then(resolve).catch(reject);
            }),
            file_picker_types: 'image media',
            file_picker_callback: (callback, value, meta) => {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = meta.filetype === 'media'
                    ? 'video/*,audio/*'
                    : 'image/*';
                input.onchange = async () => {
                    const file = input.files && input.files[0];
                    if (!file) {
                        return;
                    }
                    try {
                        const url = await uploadEditorFile(file);
                        callback(url, { title: file.name });
                    } catch (err) {
                        alert(typeof err === 'string' ? err : 'Upload failed');
                    }
                };
                input.click();
            }
        });
    } else {
        showEditorError('Failed to load rich text editor. Please refresh and try again.');
    }
</script>
</body>
</html>
