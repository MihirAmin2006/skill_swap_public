<html>
    <head>
        <title>
            Skill Swap
        </title>
        <link rel = "stylesheet" href = "./styles/styles.css" >
    </head>
    <body>
        <?php
            session_start();
            if(isset($_SESSION['error_message'])){
                echo "<h1>".$_SESSION['error_message']."Please Login here.</h1> <a href = './Auth/sign_in'>Login</a>";
                unset($_SESSION['error_message']);
            }
        ?> 
    </body>

</html>
