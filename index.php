<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HROMAN</title>
    <link rel="stylesheet" href="style.css">
    <meta name="description" content="Descripción de HROMAN">
    <meta name="keywords" content="HROMAN, empresa, servicios">
    <meta property="og:title" content="HROMAN">
    <meta property="og:description" content="Descripción de HROMAN">
    <meta property="og:image" content="image/hroman.png">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet"> <!-- Fuentes -->
    <style>
        /* Fuente predeterminada */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f7f7f7;
            color: #333;
            margin: 0;
            padding: 0;
        }

        h1, h2 {
            font-weight: 700;
            color: #333;
        }

        /* Carrusel */
        .carousel {
            position: relative;
            width: 100%;
            max-width: 401px;
            margin: auto;
            overflow: hidden;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Sombra suave */
        }

        .carousel-item {
            display: none;
            width: 401px;
            height: 401px;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
            object-fit: cover; /* Asegura que la imagen se ajuste al tamaño */
            border-radius: 8px;
        }

        .carousel-item.active {
            display: block;
            opacity: 1;
        }

        /* Botones de navegación */
        .prev, .next {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            font-size: 20px;
            border-radius: 50%;
        }

        .prev:hover, .next:hover {
            background-color: rgba(0, 0, 0, 0.7);
        }

        /* Contenedor responsivo para el carrusel y la descripción */
        .content {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            margin: 20px;
        }

        .carousel-container {
            flex: 1;
            max-width: 401px;
            margin-right: 20px;
        }

        .description-container {
            flex: 1;
            max-width: 600px;
            text-align: justify;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .description-container h2 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #006400; /* Verde para encabezados */
        }

        .description-container p {
            font-size: 16px;
            line-height: 1.6;
            color: #666;
        }

        /* Responsividad */
        @media (max-width: 768px) {
            .content {
                flex-direction: column;
                align-items: flex-start;
            }

            .carousel-container {
                margin-right: 0;
                margin-bottom: 20px;
            }

            .description-container {
                text-align: left;
            }
        }

        /* Efectos de hover */
        .carousel-item:hover {
            transform: scale(1.05); /* Efecto de zoom al pasar el mouse */
            transition: transform 0.3s ease;
        }

        .description-container:hover {
            background-color: #f1f1f1;
            transition: background-color 0.3s ease;
        }

        /* Botones destacados */
        .btn-contact {
            display: inline-block;
            padding: 12px 20px;
            background-color: #006400;
            color: white;
            font-size: 18px;
            border-radius: 8px;
            text-decoration: none;
            margin-top: 20px;
            text-align: center;
            transition: background-color 0.3s ease;
        }

        .btn-contact:hover {
            background-color: #004d00;
        }

    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main>
        <h1>Bienvenido a HROMAN</h1>

        <div class="content">
            <!-- Carrusel -->
            <div class="carousel-container">
                <div class="carousel">
                    <img src="carrusel/carrusel1.jpg" alt="Imagen 1" class="carousel-item active">
                    <img src="carrusel/carrusel2.jpg" alt="Imagen 2" class="carousel-item">
                    <img src="carrusel/carrusel3.jpg" alt="Imagen 3" class="carousel-item">
                    <img src="carrusel/carrusel4.jpg" alt="Imagen 4" class="carousel-item">
                    <img src="carrusel/carrusel5.jpg" alt="Imagen 5" class="carousel-item">
                    <img src="carrusel/carrusel6.jpg" alt="Imagen 6" class="carousel-item">
                    <img src="carrusel/carrusel7.jpg" alt="Imagen 7" class="carousel-item">
                    <button class="prev">‹</button>
                    <button class="next">›</button>
                </div>
            </div>

            <!-- Descripción -->
            <div class="description-container">
                <h2>La Constructora Hroman</h2>
                <p>La Constructora Hroman es una empresa dedicada a la construcción de obras civiles, operando entre la VI y la IX región de Chile. Se especializa en la construcción de caminos, forestales, tranques, urbanizaciones, producción de áridos, arriendo de maquinaria y transporte en general. Con más de 25 años de experiencia, la empresa se destaca por su calidad y compromiso con sus clientes.</p>
                <a href="#contact" class="btn-contact">Contáctanos</a>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let currentIndex = 0;
            const items = document.querySelectorAll('.carousel-item');
            const prevButton = document.querySelector('.prev');
            const nextButton = document.querySelector('.next');

            if (items.length > 0) {
                items[currentIndex].classList.add('active');

                // Función para cambiar la imagen activa
                function changeImage() {
                    items[currentIndex].classList.remove('active');
                    currentIndex = (currentIndex + 1) % items.length;
                    items[currentIndex].classList.add('active');
                }

                // Cambiar la imagen cada 3 segundos automáticamente
                setInterval(changeImage, 3000);

                // Navegar a la imagen anterior
                prevButton.addEventListener('click', function() {
                    items[currentIndex].classList.remove('active');
                    currentIndex = (currentIndex - 1 + items.length) % items.length;
                    items[currentIndex].classList.add('active');
                });

                // Navegar a la siguiente imagen
                nextButton.addEventListener('click', function() {
                    changeImage();
                });
            }
        });
    </script>
</body>
</html>
