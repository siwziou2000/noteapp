<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Drawing App</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #f0f0f0;
            font-family: Arial, sans-serif;
            min-height: 100vh;
            padding: 10px;
        }

        #app-container {
            width: 100%;
            max-width: 1200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0 auto;
            padding: 15px;
        }

        .canvas-container {
            width: 100%;
            position: relative;
            overflow: auto;
            border: 2px solid #333;
            background: white;
            margin-bottom: 15px;
            -webkit-overflow-scrolling: touch;
        }

        #canvas {
            display: block;
            background-color: white;
            cursor: crosshair;
            width: 100%;
            height: auto;
            max-width: 100%;
            touch-action: none;
        }

        .controls {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 15px;
        }

        .tool-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background: white;
            justify-content: center;
            align-items: center;
        }

        .tool-btn {
            padding: 12px;
            border: 2px solid #333;
            border-radius: 6px;
            cursor: pointer;
            background-color: white;
            font-size: 16px;
            min-width: 44px;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tool-btn.active {
            background-color: #4CAF50;
            color: white;
        }

        input[type="color"] {
            width: 44px;
            height: 44px;
            padding: 2px;
            cursor: pointer;
            border: 2px solid #333;
            border-radius: 6px;
        }

        input[type="range"] {
            width: 120px;
            cursor: pointer;
            height: 20px;
        }

        .range-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .range-group label {
            font-size: 12px;
            color: #666;
        }

        #clearBtn, #undoBtn, #redoBtn, #saveBtn, #savePdfBtn, #saveDbBtn {
            padding: 12px 15px;
            border: 2px solid #333;
            border-radius: 6px;
            cursor: pointer;
            background-color: white;
            font-size: 14px;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        #templatePicker {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .template-thumb {
            width: 80px;
            height: 60px;
            border: 2px solid #aaa;
            cursor: pointer;
            border-radius: 5px;
            transition: transform 0.2s ease;
            object-fit: cover;
        }

        .template-thumb:hover {
            transform: scale(1.05);
            border-color: #333;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            #app-container {
                padding: 10px;
                margin: 5px;
            }
            
            .canvas-container {
                max-height: 60vh;
            }
            
            .tool-group {
                padding: 8px;
                gap: 6px;
            }
            
            .tool-btn {
                padding: 10px;
                font-size: 14px;
                min-width: 40px;
                min-height: 40px;
            }
            
            input[type="range"] {
                width: 100px;
            }
            
            #clearBtn, #undoBtn, #redoBtn, #saveBtn, #savePdfBtn, #saveDbBtn {
                padding: 10px 12px;
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 5px;
            }
            
            #app-container {
                padding: 5px;
            }
            
            .tool-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .tool-btn {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .range-group {
                width: 100%;
            }
            
            input[type="range"] {
                width: 100%;
            }
            
            #clearBtn, #undoBtn, #redoBtn, #saveBtn, #savePdfBtn, #saveDbBtn {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .button-group-mobile {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 5px;
                width: 100%;
            }
        }

        @media (min-width: 1200px) {
            #canvas {
                max-width: 1000px;
                max-height: 600px;
            }
        }

        /* Scrollbar styling for canvas container */
        .canvas-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .canvas-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .canvas-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .canvas-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* High DPI screens */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .tool-btn {
                font-size: 18px;
            }
        }

        /* Landscape orientation for mobile */
        @media (max-height: 500px) and (orientation: landscape) {
            .canvas-container {
                max-height: 70vh;
            }
            
            .controls {
                flex-direction: row;
                flex-wrap: wrap;
            }
            
            .tool-group {
                flex: 1;
                min-width: 200px;
            }
        }
    </style>
</head>
<body>
    <div id="app-container">
        <div class="canvas-container">
            <canvas id="canvas"></canvas>
        </div>
        
        <div class="controls">
            <div class="tool-group">
                <button class="tool-btn active" data-tool="pencil" title="Μολύβι">✏️</button>
                <button class="tool-btn" data-tool="eraser" title="Γομολάστιχα">🧹</button>
                <button class="tool-btn" data-tool="rectangle" title="Ορθογώνιο">⬜</button>
                <button class="tool-btn" data-tool="circle" title="Κύκλος">⭕</button>
                <button class="tool-btn" data-tool="line" title="Γραμμή">📏</button>
                
            </div>
            
            <div class="tool-group">
                <input type="color" id="colorPicker" value="#000000" title="Επιλογή χρώματος">
                <div class="range-group">
                    <input type="range" id="brushSize" min="1" max="50" value="5" title="Μέγεθος πινέλου">
                    <label for="brushSize">Μέγεθος</label>
                </div>
                <div class="range-group">
                    <input type="range" id="opacity" min="0.1" max="1" step="0.1" value="1" title="Διαφάνεια">
                    <label for="opacity">Διαφάνεια</label>
                </div>
            </div>

            <div class="tool-group button-group-mobile">
                <button id="clearBtn">🧼 Καθαρισμός</button>
                <button id="undoBtn">↩️ Undo</button>
                <button id="redoBtn">↪️ Redo</button>
                <button id="saveBtn">💾 Αποθήκευση</button>
                <button id="savePdfBtn">📝 PDF</button>
                <button id="saveDbBtn">💾  Αποθηκευση στη Βάση</button>
            </div>
        </div>
    </div>

    <script>
        const canvas = document.getElementById('canvas');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        let currentTool = 'pencil';
        let history = [];
        let historyStep = -1;
        let lastUpdateTimestamp = 0;
        let saveDebounce = null;
        
        // Responsive canvas setup
        function setupCanvas() {
            const container = document.querySelector('.canvas-container');
            const maxWidth = container.clientWidth - 4; // Account for border
            const maxHeight = window.innerHeight * 0.6; // 60% of viewport height
            
            // Set canvas dimensions
            canvas.width = Math.min(800, maxWidth);
            canvas.height = Math.min(500, maxHeight);
            
            // Clear and set background
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // Reload previous state if exists
            loadFromLocalStorage();
        }

        // Tools configuration
        let startX, startY, lastX, lastY;
        let snapshot;

        // Event Listeners
        document.querySelectorAll('.tool-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentTool = btn.dataset.tool;
            });
        });

        // Mouse events
        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', end);
        canvas.addEventListener('mouseout', end);

        // Touch events
        canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent('mousedown', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        });

        canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent('mousemove', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        });

        canvas.addEventListener('touchend', (e) => {
            e.preventDefault();
            const mouseEvent = new MouseEvent('mouseup', {});
            canvas.dispatchEvent(mouseEvent);
        });

        // Tools functions
        function start(e) {
            isDrawing = true;
            const rect = canvas.getBoundingClientRect();
            startX = e.clientX - rect.left;
            startY = e.clientY - rect.top;
            lastX = startX;
            lastY = startY;
            
            if(currentTool !== 'pencil' && currentTool !== 'eraser') {
                snapshot = ctx.getImageData(0, 0, canvas.width, canvas.height);
            }
        }

        function draw(e) {
            if(!isDrawing) return;
            
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            ctx.globalAlpha = document.getElementById('opacity').value;
            ctx.strokeStyle = document.getElementById('colorPicker').value;
            ctx.lineWidth = document.getElementById('brushSize').value;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            switch(currentTool) {
                case 'pencil':
                    ctx.beginPath();
                    ctx.moveTo(lastX, lastY);
                    ctx.lineTo(x, y);
                    ctx.stroke();
                    [lastX, lastY] = [x, y];
                    break;
                
                case 'eraser':
                    ctx.save();
                    ctx.globalCompositeOperation = 'destination-out';
                    ctx.beginPath();
                    ctx.arc(x, y, ctx.lineWidth/2, 0, Math.PI*2);
                    ctx.fill();
                    ctx.restore();
                    break;
                
                case 'rectangle':
                    ctx.putImageData(snapshot, 0, 0);
                    ctx.beginPath();
                    ctx.rect(startX, startY, x - startX, y - startY);
                    ctx.stroke();
                    break;
                
                case 'circle':
                    ctx.putImageData(snapshot, 0, 0);
                    ctx.beginPath();
                    const radius = Math.sqrt(Math.pow(x - startX, 2) + Math.pow(y - startY, 2));
                    ctx.arc(startX, startY, radius, 0, Math.PI*2);
                    ctx.stroke();
                    break;
                
                case 'line':
                    ctx.putImageData(snapshot, 0, 0);
                    ctx.beginPath();
                    ctx.moveTo(startX, startY);
                    ctx.lineTo(x, y);
                    ctx.stroke();
                    break;

               
               
            }
        }

        function end() {
            isDrawing = false;
            if(currentTool !== 'fill') saveState();
        }

        // Flood fill algorithm
        function floodFill(x, y) {
            const targetColor = ctx.getImageData(x, y, 1, 1).data;
            const fillColor = hexToRgb(document.getElementById('colorPicker').value);
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const stack = [[x, y]];
            
            while(stack.length) {
                const [cx, cy] = stack.pop();
                const pos = (cy * canvas.width + cx) * 4;
                
                if(!colorMatch(imageData.data.slice(pos, pos+3), targetColor)) continue;
                
                imageData.data[pos] = fillColor.r;
                imageData.data[pos+1] = fillColor.g;
                imageData.data[pos+2] = fillColor.b;
                imageData.data[pos+3] = 255;
                
                if(cx > 0) stack.push([cx-1, cy]);
                if(cx < canvas.width-1) stack.push([cx+1, cy]);
                if(cy > 0) stack.push([cx, cy-1]);
                if(cy < canvas.height-1) stack.push([cx, cy+1]);
            }
            ctx.putImageData(imageData, 0, 0);
        }

        // Helper functions
        function hexToRgb(hex) {
            const bigint = parseInt(hex.slice(1), 16);
            return {
                r: (bigint >> 16) & 255,
                g: (bigint >> 8) & 255,
                b: bigint & 255
            };
        }

        function colorMatch(a, b) {
            return a[0] === b[0] && a[1] === b[1] && a[2] === b[2];
        }

        // Undo/Redo functionality
        function saveState() {
            historyStep++;
            if(historyStep < history.length) history.length = historyStep;
            history.push(canvas.toDataURL());
            localStorage.setItem('canvasState', canvas.toDataURL());
            localStorage.setItem('canvasHistory', JSON.stringify(history));
            localStorage.setItem('historyStep', historyStep);
            
            // Auto-save with debounce
            if (!saveDebounce) {
                saveDebounce = setTimeout(async () => {
                    await saveToDatabase();
                    saveDebounce = null;
                }, 1000);
            }
        }

        // Load from localStorage
        function loadFromLocalStorage() {
            const savedState = localStorage.getItem('canvasState');
            const savedHistory = localStorage.getItem('canvasHistory');
            const savedStep = localStorage.getItem('historyStep');
            
            if (savedState) {
                const img = new Image();
                img.onload = () => {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0);
                };
                img.src = savedState;
            }
            
            if (savedHistory) {
                history = JSON.parse(savedHistory);
                historyStep = parseInt(savedStep) || history.length - 1;
            }
        }

        document.getElementById('undoBtn').addEventListener('click', () => {
            if(historyStep > 0) {
                historyStep--;
                const img = new Image();
                img.src = history[historyStep];
                img.onload = () => ctx.drawImage(img, 0, 0);
            }
        });

        document.getElementById('redoBtn').addEventListener('click', () => {
            if(historyStep < history.length-1) {
                historyStep++;
                const img = new Image();
                img.src = history[historyStep];
                img.onload = () => ctx.drawImage(img, 0, 0);
            }
        });

        // Clear and Save
        document.getElementById('clearBtn').addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            saveState();
        });

        document.getElementById('saveBtn').addEventListener('click', () => {
            const link = document.createElement('a');
            link.download = 'drawing.png';
            link.href = canvas.toDataURL();
            link.click();
        });

        document.getElementById('savePdfBtn').addEventListener('click', () => {
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF({
                orientation: "landscape",
                unit: "px",
                format: [canvas.width, canvas.height]
            });
            pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, canvas.width, canvas.height);
            pdf.save('drawing.pdf');
        });

        // Database functions
        async function saveToDatabase() {
            const dataUrl = canvas.toDataURL('image/png');
            
            try {
                const response = await fetch('save.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ image: dataUrl })
                });
                
                const result = await response.json();
                if (result.success) {
                    console.log('Αποθήκευση επιτυχής:', result.message);
                    lastUpdateTimestamp = result.timestamp || Date.now();
                    return true;
                } else {
                    console.error('Σφάλμα αποθήκευσης:', result.message);
                    return false;
                }
            } catch (error) {
                console.error('Σφάλμα δικτύου:', error);
                return false;
            }
        }

        async function loadLatestCanvas() {
            try {
                const response = await fetch(`get_latest_canvas.php?lastUpdate=${lastUpdateTimestamp}`);
                const data = await response.json();
                
                if (data.success && data.image && data.timestamp > lastUpdateTimestamp) {
                    lastUpdateTimestamp = data.timestamp;
                    const img = new Image();
                    img.onload = function() {
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(img, 0, 0);
                        saveState();
                    };
                    img.src = 'data:image/png;base64,' + data.image;
                }
            } catch (error) {
                console.error('Σφάλμα φόρτωσης:', error);
            }
        }

        // Auto-save and sync setup
        function setupAutoSaveAndSync() {
            // Check for updates every 2 seconds
            setInterval(loadLatestCanvas, 2000);
            
            // Force save every 30 seconds
            setInterval(async () => {
                await saveToDatabase();
            }, 30000);
            
            // Save on window close
            window.addEventListener('beforeunload', async () => {
                await saveToDatabase();
            });
        }

        // Initialize on load
        window.addEventListener('load', async () => {
            // Setup responsive canvas
            setupCanvas();
            
            // Add window resize listener
            window.addEventListener('resize', setupCanvas);
            
            // Load from database
            await loadLatestCanvas();
            
            // Start auto-save and sync
            setupAutoSaveAndSync();
            
            // Save initial state if empty
            if (history.length === 0) {
                saveState();
            }
        });

        // Manual save button
        document.getElementById('saveDbBtn').addEventListener('click', async () => {
            const success = await saveToDatabase();
            alert(success ? 'Η εικόνα αποθηκεύτηκε επιτυχώς!' : 'Προέκυψε σφάλμα κατά την αποθήκευση');
        });

        // Prevent scrolling when touching canvas
        document.body.addEventListener('touchstart', (e) => {
            if(e.target === canvas) e.preventDefault();
        }, { passive: false });
    </script>
</body>
</html>