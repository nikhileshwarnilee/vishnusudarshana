<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Button Design Samples</title>
    <?php include 'header.php'; ?>
    <style>
        body {
            background: #fffbe6;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        .designs-container {
            max-width: 900px;
            margin: 40px auto 0 auto;
            padding: 24px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(212,175,55,0.08);
        }
        .designs-title {
            text-align: center;
            font-size: 2rem;
            color: #8B1538;
            margin-bottom: 32px;
            font-weight: 800;
        }
        .button-row {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            justify-content: center;
            margin-bottom: 32px;
        }
        .sample-btn {
            padding: 14px 36px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.18s cubic-bezier(.4,1.3,.6,1);
            box-shadow: 0 2px 8px rgba(212,175,55,0.10);
            outline: none;
            margin-bottom: 8px;
        }
        /* Button 1: Shiny Gold */
        .btn-gold {
            background: linear-gradient(90deg, #FFD700 0%, #FFFACD 100%);
            color: #8B1538;
            border: 2px solid #d4af37;
        }
        .btn-gold:hover, .btn-gold:focus {
            background: #fffbe6;
            color: #b8960f;
            border-color: #b8960f;
        }
        /* Button 2: Maroon */
        .btn-maroon {
            background: #8B1538;
            color: #fffbe6;
            border: 2px solid #FFD700;
        }
        .btn-maroon:hover, .btn-maroon:focus {
            background: #6B0E2E;
            color: #FFD700;
            border-color: #b8960f;
        }
        /* Button 3: White with Gold Border */
        .btn-white-gold {
            background: #fff;
            color: #8B1538;
            border: 2px solid #FFD700;
        }
        .btn-white-gold:hover, .btn-white-gold:focus {
            background: #FFFACD;
            color: #b8960f;
            border-color: #d4af37;
        }
        /* Button 4: Gradient Maroon-Gold */
        .btn-gradient {
            background: linear-gradient(90deg, #8B1538 0%, #FFD700 100%);
            color: #fff;
            border: none;
        }
        .btn-gradient:hover, .btn-gradient:focus {
            background: linear-gradient(90deg, #FFD700 0%, #8B1538 100%);
            color: #8B1538;
        }
        /* Button 5: Outlined Maroon */
        .btn-outline-maroon {
            background: transparent;
            color: #8B1538;
            border: 2px solid #8B1538;
        }
        .btn-outline-maroon:hover, .btn-outline-maroon:focus {
            background: #8B1538;
            color: #FFD700;
        }
        /* Button 6: Outlined Gold */
        .btn-outline-gold {
            background: transparent;
            color: #FFD700;
            border: 2px solid #FFD700;
        }
        .btn-outline-gold:hover, .btn-outline-gold:focus {
            background: #FFD700;
            color: #8B1538;
        }
        /* Button 7: Soft Yellow */
        .btn-soft-yellow {
            background: #FFFACD;
            color: #8B1538;
            border: 2px solid #FFD700;
        }
        .btn-soft-yellow:hover, .btn-soft-yellow:focus {
            background: #FFD700;
            color: #fff;
        }
        /* Button 8: Maroon Shadow */
        .btn-maroon-shadow {
            background: #8B1538;
            color: #FFD700;
            border: none;
            box-shadow: 0 4px 16px rgba(139,21,56,0.18);
        }
        .btn-maroon-shadow:hover, .btn-maroon-shadow:focus {
            background: #6B0E2E;
            color: #fffbe6;
        }
        /* Responsive */
        @media (max-width: 600px) {
            .designs-container {
                padding: 8px;
            }
            .button-row {
                flex-direction: column;
                gap: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="designs-container">
        <div class="designs-title">Button Design Samples</div>
        <div class="button-row">
            <button class="sample-btn btn-gold">Shiny Gold</button>
            <button class="sample-btn btn-maroon">Maroon</button>
            <button class="sample-btn btn-white-gold">White with Gold Border</button>
            <button class="sample-btn btn-gradient">Gradient Maroon-Gold</button>
        </div>
        <div class="button-row">
            <button class="sample-btn btn-outline-maroon">Outlined Maroon</button>
            <button class="sample-btn btn-outline-gold">Outlined Gold</button>
            <button class="sample-btn btn-soft-yellow">Soft Yellow</button>
            <button class="sample-btn btn-maroon-shadow">Maroon Shadow</button>
        </div>
    </div>
</body>
</html>
