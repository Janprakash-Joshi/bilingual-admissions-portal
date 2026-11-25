<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>404 - Not Found</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family: 'Segoe UI', Arial, sans-serif;
}
body{
    min-height:100vh;
    background:linear-gradient(135deg, #0f2027, #203a43, #2c5364);
    display:flex;
    justify-content:center;
    align-items:center;
    color:white;
}
.container{
    text-align:center;
    max-width:500px;
    padding:40px;
    background:rgba(0,0,0,0.4);
    border-radius:15px;
    box-shadow:0 0 40px rgba(0,0,0,0.6);
    animation: fadeIn 1s ease;
}
.error{
    font-size:120px;
    font-weight:900;
    letter-spacing:10px;
    color:#ff4757;
    text-shadow:0 0 30px rgba(255,71,87,0.8);
}
h2{
    margin-top:10px;
    font-size:28px;
}
p{
    margin-top:15px;
    color:#ddd;
    line-height:1.6;
}
.btn{
    margin-top:30px;
    display:inline-block;
    padding:12px 35px;
    font-size:18px;
    border:none;
    border-radius:30px;
    background:#1e90ff;
    color:white;
    cursor:pointer;
    box-shadow:0 0 20px rgba(30,144,255,0.7);
    transition:0.3s;
}
.btn:hover{
    background:#00a8ff;
    transform:scale(1.05);
}
@keyframes fadeIn{
    from{opacity:0; transform:scale(0.9);}
    to{opacity:1; transform:scale(1);}
}
</style>
</head>

<body>
<div class="container">
    <div class="error">404</div>
    <h2>Resource Not Found</h2>
    <p>
        The page you are trying to access <br>
        may have been <b>removed, renamed, or deleted</b>.
    </p>
    <button class="btn" onclick="goBack()">Exit</button>
</div>

<script>

async function goBack(){
    let path = window.location.pathname;

    while (path.length > 1) {
        // Remove last part
        path = path.replace(/\/[^\/]+$/, '');

        if(path === '') path = '/';

        try {
            let res = await fetch(path, { method: 'HEAD' });

            if (res.ok) {
                window.location.href = path;
                return;
            }
        } catch(e){}
    }

    // fallback
    window.location.href = "/";
}




</script>

</body>
</html>
