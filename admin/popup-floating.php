<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Floating Popup</title>
    <meta name="viewport" content="width=600, initial-scale=1.0">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kalam:wght@400&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
        .popup-container { max-width: 520px; margin: 32px auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px #80000033; padding: 32px 24px; }
        h2 { color: #800000; margin-bottom: 18px; }
        .close-btn { background: #800000; color: #fff; border: none; border-radius: 8px; padding: 8px 22px; font-weight: 600; cursor: pointer; float: right; }
    </style>
</head>
<body>
    <div class="popup-container">
        <button class="close-btn" onclick="window.close()">Close</button>
        <h2>Floating Popup</h2>

        <?php
        require_once __DIR__ . '/../config/db.php';
        $stmt = $pdo->query('SELECT id, title FROM letterpad_titles ORDER BY title ASC');
        $optionsHtml = '<option value=""></option>';
        foreach ($stmt as $row) {
            $id = htmlspecialchars($row['id']);
            $title = htmlspecialchars($row['title']);
            $optionsHtml .= "<option value=\"$id\">$title</option>";
        }
        ?>

        <div id="rtSections"></div>

        <div style="margin-top:16px;text-align:left; display:flex; gap:10px;">
            <button type="button" id="addMoreBtn" style="background:#25D366;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Add More</button>
            <button type="button" id="printBtn" style="background:#800000;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Print</button>
            <button type="button" id="downloadBtn" style="background:#007bff;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Download</button>
        </div>

        <div style="color:#444; font-size:1.1em; margin-top:18px;">This is a new popup window opened from the floating icon.<br><br>You can customize this page as needed.</div>
    </div>

    <script>
    const optionsHtml = <?php echo json_encode($optionsHtml); ?>;
    const sectionsContainer = document.getElementById('rtSections');
    const addBtn = document.getElementById('addMoreBtn');
    const printBtn = document.getElementById('printBtn');
    const downloadBtn = document.getElementById('downloadBtn');
    let sectionIndex = 1;

    function ensureKalamFontLoaded() {
        return new Promise(resolve => {
            const linkId = 'kalam-font-link';
            let link = document.getElementById(linkId);
            if (!link) {
                link = document.createElement('link');
                link.id = linkId;
                link.rel = 'stylesheet';
                link.href = 'https://fonts.googleapis.com/css2?family=Kalam:wght@400&display=swap';
                document.head.appendChild(link);
            }

            if (document.fonts && document.fonts.load) {
                Promise.all([
                    document.fonts.load("400 18px 'Kalam'"),
                    document.fonts.load("400 24px 'Kalam'")
                ]).then(() => resolve()).catch(() => resolve());
            } else {
                link.onload = () => resolve();
                setTimeout(resolve, 500);
            }
        });
    }

    function createSection() {
        const idx = sectionIndex++;
        const section = document.createElement('div');
        section.className = 'rt-section';
        section.style.marginBottom = '18px';
        section.innerHTML = `
            <div style="text-align:right; margin-bottom:6px;">
                <button type="button" class="remove-section" title="Remove this section" style="background:#ffdddd; color:#a00; border:1px solid #e0a0a0; border-radius:6px; padding:4px 10px; cursor:pointer; font-weight:600;">✕</button>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-weight:600;color:#800000;">Select Title:</label><br>
                <select name="title_${idx}" style="margin-top:8px;padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;">
                    ${optionsHtml}
                </select>
            </div>
            <div>
                <label style="font-weight:600;color:#800000;">Message:</label><br>
                <div class="toolbar" data-target="richText_${idx}" style="margin-bottom:6px; display:flex; gap:6px; flex-wrap:wrap;">
                    <button type="button" title="Bold" data-cmd="bold" style="font-weight:bold;">B</button>
                    <button type="button" title="Italic" data-cmd="italic" style="font-style:italic;">I</button>
                    <button type="button" title="Underline" data-cmd="underline" style="text-decoration:underline;">U</button>
                    <button type="button" title="Strikethrough" data-cmd="strikeThrough"><span style="text-decoration:line-through;">S</span></button>
                    <button type="button" title="Ordered List" data-cmd="insertOrderedList">OL</button>
                    <button type="button" title="Unordered List" data-cmd="insertUnorderedList">UL</button>
                    <button type="button" title="Link" data-cmd="createLink" data-prompt="url">🔗</button>
                    <button type="button" title="Remove Link" data-cmd="unlink">❌🔗</button>
                    <button type="button" title="Undo" data-cmd="undo">⎌</button>
                    <button type="button" title="Redo" data-cmd="redo">⎌⎌</button>
                    <button type="button" title="Left Align" data-cmd="justifyLeft">⯇</button>
                    <button type="button" title="Center Align" data-cmd="justifyCenter">≡</button>
                    <button type="button" title="Right Align" data-cmd="justifyRight">⯈</button>
                    <button type="button" title="Remove Format" data-cmd="removeFormat">Tx</button>
                </div>
                <div id="richText_${idx}" contenteditable="true" style="min-height:120px;padding:12px;border-radius:8px;border:1px solid #ccc;font-size:1.08em;background:#f9f9fc;outline:none; margin-top:8px;" placeholder="Type your message here..."></div>
            </div>
        `;

        // Attach toolbar handlers
        section.querySelectorAll('.toolbar button').forEach(btn => {
            btn.onmousedown = e => e.preventDefault();
            btn.onclick = () => {
                const targetId = btn.parentElement.dataset.target;
                const target = document.getElementById(targetId);
                if (!target) return;
                target.focus();
                const cmd = btn.dataset.cmd;
                if (cmd === 'createLink') {
                    const url = prompt('Enter the URL:', 'https://');
                    if (url) document.execCommand(cmd, false, url);
                } else {
                    document.execCommand(cmd, false, null);
                }
            };
        });

        // Remove section handler
        const removeBtn = section.querySelector('.remove-section');
        removeBtn.addEventListener('click', () => {
            section.remove();
        });

        // Track focus for execCommand reliability
        const editable = section.querySelector('[contenteditable]');
        editable.addEventListener('focus', () => {
            // placeholder behavior can be added if needed
        });

        sectionsContainer.appendChild(section);
    }

    addBtn.addEventListener('click', createSection);
    printBtn.addEventListener('click', () => {
        const sections = Array.from(sectionsContainer.querySelectorAll('.rt-section'));
        const headerImg = window.location.origin + '/vishnusudarshana/vishnusudarshana/admin/includes/compbanner.jpg';
        let html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Print</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Kalam:wght@400&display=swap" rel="stylesheet"><style>@page{margin:0;}html,body{margin:0;padding:0;background:#fffbf0;font-family:Arial,sans-serif;}body{display:flex;flex-direction:column;min-height:100vh;} .page-body{flex:1;display:flex;flex-direction:column;} .content{padding:20px;padding-bottom:30px;margin:30px 50px;flex:1;} .welcome-note{text-align:center;padding:20px;font-size:18px;font-weight:600;color:#800000;} .section-title{font-family:\'Kalam\',cursive;font-weight:400;font-size:24px;color:#800000;} .section-content{font-family:\'Kalam\',cursive;font-weight:400;font-size:18px;} .footer{position:relative;width:100%;padding:20px 50px;border-top:2px solid #ddd;font-size:14px;line-height:1.6;background:#fffbf0;box-sizing:border-box;page-break-inside:avoid;margin-top:auto;}</style></head><body>';
        html += '<div class="page-body">';
        html += `<img src="${headerImg}" alt="Header" style="width:100%;display:block;margin:0;padding:0;">`;
        html += '<div class="welcome-note">Welcome to VishnuSudarshana</div>';
        html += '<div class="content">';
        sections.forEach((section, idx) => {
            const titleSel = section.querySelector('select');
            const selectedText = titleSel ? titleSel.options[titleSel.selectedIndex]?.text || '' : '';
            const editor = section.querySelector('[contenteditable]');
            const content = editor ? editor.innerHTML : '';
            html += `<div style="margin-bottom:20px;">
                <div class="section-title" style="font-weight:bold;margin-bottom:8px;">${selectedText}</div>
                <div class="section-content">${content}</div>
            </div>`;
        });
        html += '</div>'; // end content
        html += '</div>'; // end page-body
        html += '<div class="footer"><p><strong>We believe in clarity, peace of mind, and genuine support for every devotee, family, and seeker. Thank you for trusting us to serve your spiritual needs.</strong></p><p><strong>Contact Us</strong><br>98500 57444<br>Shubhamastu Nilayam, Plot No 63,<br>Srushti Nagar, Akkalkot Road,<br>Solapur - 413006</p></div>';
        html += '</body></html>';

        const w = window.open('', 'PrintView');
        w.document.open();
        w.document.write(html);
        w.document.close();
        
        // Wait for all resources to load before printing
        w.addEventListener('load', () => {
            // Wait for fonts to load
            if (w.document.fonts) {
                w.document.fonts.ready.then(() => {
                    // Additional delay to ensure images are rendered
                    setTimeout(() => {
                        w.focus();
                        w.print();
                    }, 500);
                });
            } else {
                // Fallback for browsers without Font Loading API
                setTimeout(() => {
                    w.focus();
                    w.print();
                }, 1000);
            }
        });
    });
    downloadBtn.addEventListener('click', () => {
        ensureKalamFontLoaded().then(() => {
            const sections = Array.from(sectionsContainer.querySelectorAll('.rt-section'));
            const headerImg = window.location.origin + '/vishnusudarshana/vishnusudarshana/admin/includes/compbanner.jpg';
            
            // Create proper DOM elements for PDF
            const pdfContainer = document.createElement('div');
            pdfContainer.style.backgroundColor = '#fffbf0';
            pdfContainer.style.fontFamily = 'Arial, sans-serif';
            pdfContainer.style.display = 'flex';
            pdfContainer.style.flexDirection = 'column';
            pdfContainer.style.width = '210mm';
            pdfContainer.style.padding = '0';
            pdfContainer.style.minHeight = '296mm';
            
            // Main wrapper
            const mainWrap = document.createElement('div');
            mainWrap.style.display = 'flex';
            mainWrap.style.flexDirection = 'column';
            mainWrap.style.flex = '1';
            mainWrap.style.pageBreakInside = 'avoid';
            
            // Header image
            const img = document.createElement('img');
            img.src = headerImg;
            img.style.width = '100%';
            img.style.display = 'block';
            img.style.margin = '0';
            img.style.padding = '0';
            mainWrap.appendChild(img);
            
            // Welcome note
            const welcomeNote = document.createElement('div');
            welcomeNote.style.textAlign = 'center';
            welcomeNote.style.padding = '20px';
            welcomeNote.style.fontSize = '18px';
            welcomeNote.style.fontWeight = '600';
            welcomeNote.style.color = '#800000';
            welcomeNote.style.fontFamily = "'Kalam', cursive";
            welcomeNote.textContent = 'Welcome to VishnuSudarshana';
            mainWrap.appendChild(welcomeNote);
            
            // Content wrapper
            const contentWrapper = document.createElement('div');
            contentWrapper.style.padding = '20px';
            contentWrapper.style.paddingBottom = '30px';
            contentWrapper.style.margin = '30px 50px';
            contentWrapper.style.flex = '1';
            
            // Add sections
            sections.forEach((section, idx) => {
                const titleSel = section.querySelector('select');
                const selectedText = titleSel ? titleSel.options[titleSel.selectedIndex]?.text || '' : '';
                const editor = section.querySelector('[contenteditable]');
                const content = editor ? editor.innerHTML : '';
                
                const sectionDiv = document.createElement('div');
                sectionDiv.style.marginBottom = '20px';
                
                const titleDiv = document.createElement('div');
                titleDiv.style.fontFamily = "'Kalam', cursive";
                titleDiv.style.fontWeight = '400';
                titleDiv.style.fontSize = '24px';
                titleDiv.style.color = '#800000';
                titleDiv.style.fontWeight = 'bold';
                titleDiv.style.marginBottom = '8px';
                titleDiv.textContent = selectedText;
                sectionDiv.appendChild(titleDiv);
                
                const contentDiv = document.createElement('div');
                contentDiv.style.fontFamily = "'Kalam', cursive";
                contentDiv.style.fontWeight = '400';
                contentDiv.style.fontSize = '18px';
                contentDiv.innerHTML = content;
                sectionDiv.appendChild(contentDiv);
                
                contentWrapper.appendChild(sectionDiv);
            });
            
            mainWrap.appendChild(contentWrapper);
            pdfContainer.appendChild(mainWrap);
            
            // Footer
            const footer = document.createElement('div');
            footer.style.position = 'relative';
            footer.style.width = '100%';
            footer.style.padding = '20px 50px';
            footer.style.borderTop = '2px solid #ddd';
            footer.style.fontSize = '14px';
            footer.style.lineHeight = '1.6';
            footer.style.backgroundColor = '#fffbf0';
            footer.style.boxSizing = 'border-box';
            footer.style.pageBreakInside = 'avoid';
            footer.style.marginTop = 'auto';
            footer.style.flexShrink = '0';
            footer.innerHTML = `
                <p><strong>We believe in clarity, peace of mind, and genuine support for every devotee, family, and seeker. Thank you for trusting us to serve your spiritual needs.</strong></p>
                <p><strong>Contact Us</strong><br>98500 57444<br>Shubhamastu Nilayam, Plot No 63,<br>Srushti Nagar, Akkalkot Road,<br>Solapur - 413006</p>
            `;
            pdfContainer.appendChild(footer);
            
            // Wait for images
            const waitForImages = root => {
                const imgs = Array.from(root.querySelectorAll('img'));
                if (!imgs.length) return Promise.resolve();
                return Promise.all(imgs.map(im => {
                    if (im.complete && im.naturalWidth) return Promise.resolve();
                    return new Promise(res => {
                        im.onload = () => res();
                        im.onerror = () => res();
                    });
                }));
            };
            
            waitForImages(pdfContainer).then(() => {
                const opt = {
                    margin: 0,
                    filename: 'letterpad_' + new Date().getTime() + '.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true, backgroundColor: '#fffbf0' },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };
                
                html2pdf().set(opt).from(pdfContainer).save();
            });
        });
    });
    // Initialize first section
    createSection();
    </script>
</body>
</html>
