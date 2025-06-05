<!DOCTYPE html>
<html>

<head>
    <title>Test Checkout API</title>
</head>

<body>
    <h1>Test Checkout API</h1>
    <button onclick="testCheckout()">Test Checkout</button>
    <div id="result"></div>

    <script>
        async function testCheckout() {
            try {
                const response = await fetch('/attendance/process_checkout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        qr_code: '123',
                        action: 'checkout',
                        user_agent: navigator.userAgent,
                        timestamp: Date.now()
                    })
                });

                const data = await response.json();
                document.getElementById('result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            } catch (error) {
                document.getElementById('result').innerHTML = 'Error: ' + error.message;
            }
        }
    </script>
</body>

</html>