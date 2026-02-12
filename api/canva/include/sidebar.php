<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Miro-Style Whiteboard</title>
    <style>
        body {
            margin: 0;
            display: flex;
            height: 100vh;
            font-family: Arial, sans-serif;
        }

        /* Sidebar */
        #sidebar {
            width: 60px;
            background: #f5f6f8;
            padding: 10px;
            border-right: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .tool-btn {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .tool-btn.active {
            background: #e3f2fd;
            color: #2196f3;
        }

        /* Main Canvas Area */
        #canvas-container {
            flex: 1;
            position: relative;
            overflow: hidden;
            background: #fafafa;
        }

        /* Draggable Elements */
        .draggable {
            position: absolute;
            cursor: grab;
            transition: box-shadow 0.2s;
            background: white;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .draggable:active {
            cursor: grabbing;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .sticky-note {
            width: 200px;
            height: 150px;
            padding: 15px;
            resize: both;
            overflow: hidden;
        }

        .shape {
            width: 100px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div id="sidebar">
        <button class="tool-btn active" data-tool="select">↖️</button>
        <button class="tool-btn" data-tool="pencil">✏️</button>
        <button class="tool-btn" data-tool="text">📝</button>
        <button class="tool-btn" data-tool="note">📌</button>
        <button class="tool-btn" data-tool="rectangle">⬜</button>
        <button class="tool-btn" data-tool="circle">⭕</button>
        <button class="tool-btn" data-tool="line">📏</button>
    </div>

    <!-- Main Canvas -->
    <div id="canvas-container"></div>

    <script>
        let currentTool = 'select';
        let isDrawing = false;
        let startX, startY;
        let currentElement = null;

        // Tool Event Listeners
        document.querySelectorAll('.tool-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentTool = btn.dataset.tool;
            });
        });

        // Canvas Events
        const canvasContainer = document.getElementById('canvas-container');
        
        canvasContainer.addEventListener('mousedown', startAction);
        canvasContainer.addEventListener('mousemove', performAction);
        canvasContainer.addEventListener('mouseup', endAction);
        canvasContainer.addEventListener('mouseleave', endAction);

        function startAction(e) {
            isDrawing = true;
            const rect = canvasContainer.getBoundingClientRect();
            startX = e.clientX - rect.left;
            startY = e.clientY - rect.top;

            switch(currentTool) {
                case 'pencil':
                    createDrawingElement();
                    break;
                case 'note':
                    createStickyNote(startX, startY);
                    break;
                case 'rectangle':
                    createShape('rectangle', startX, startY);
                    break;
                case 'circle':
                    createShape('circle', startX, startY);
                    break;
                case 'line':
                    createShape('line', startX, startY);
                    break;
                case 'text':
                    createTextElement(startX, startY);
                    break;
            }
        }

        function performAction(e) {
            if (!isDrawing) return;
            
            const rect = canvasContainer.getBoundingClientRect();
            const currentX = e.clientX - rect.left;
            const currentY = e.clientY - rect.top;

            if (currentTool === 'pencil' && currentElement) {
                drawLine(currentX, currentY);
            }
        }

        function endAction() {
            isDrawing = false;
            currentElement = null;
        }

        // Element Creation Functions
        function createStickyNote(x, y) {
            const note = document.createElement('div');
            note.className = 'draggable sticky-note';
            note.style.left = `${x}px`;
            note.style.top = `${y}px`;
            note.contentEditable = true;
            note.innerHTML = 'Κάντε κλικ για να γράψετε...';
            canvasContainer.appendChild(note);
            makeDraggable(note);
        }

        function createShape(type, x, y) {
            const shape = document.createElement('div');
            shape.className = `draggable shape ${type}`;
            shape.style.left = `${x}px`;
            shape.style.top = `${y}px`;
            
            switch(type) {
                case 'rectangle':
                    shape.style.border = '2px solid #2196f3';
                    break;
                case 'circle':
                    shape.style.border = '2px solid #4caf50';
                    shape.style.borderRadius = '50%';
                    break;
                case 'line':
                    shape.style.width = '200px';
                    shape.style.height = '2px';
                    shape.style.backgroundColor = '#f44336';
                    break;
            }
            
            canvasContainer.appendChild(shape);
            makeDraggable(shape);
        }

        // Drawing Functions
        function createDrawingElement() {
            currentElement = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            currentElement.style.position = 'absolute';
            currentElement.style.left = `${startX}px`;
            currentElement.style.top = `${startY}px`;
            currentElement.style.width = '100%';
            currentElement.style.height = '100%';
            currentElement.style.pointerEvents = 'none';
            
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('stroke', '#000000');
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke-width', '2');
            path.setAttribute('d', `M 0 0`);
            currentElement.appendChild(path);
            
            canvasContainer.appendChild(currentElement);
        }

        function drawLine(x, y) {
            const path = currentElement.querySelector('path');
            const newPath = path.getAttribute('d') + ` L ${x - startX} ${y - startY}`;
            path.setAttribute('d', newPath);
        }

        // Draggable Functionality
        function makeDraggable(element) {
            let isDragging = false;
            let offsetX, offsetY;

            element.addEventListener('mousedown', (e) => {
                if (currentTool !== 'select') return;
                
                isDragging = true;
                const rect = element.getBoundingClientRect();
                offsetX = e.clientX - rect.left;
                offsetY = e.clientY - rect.top;
                element.style.zIndex = 1000;
            });

            document.addEventListener('mousemove', (e) => {
                if (isDragging && currentTool === 'select') {
                    const rect = canvasContainer.getBoundingClientRect();
                    const x = e.clientX - rect.left - offsetX;
                    const y = e.clientY - rect.top - offsetY;
                    
                    element.style.left = `${x}px`;
                    element.style.top = `${y}px`;
                }
            });

            document.addEventListener('mouseup', () => {
                isDragging = false;
                element.style.zIndex = 1;
            });
        }
    </script>
</body>
</html>