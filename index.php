<?php require "./php/init.php"; ?>
<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">
<title>Wallet Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<script src="https://cdn.jsdelivr.net/npm/ethers@6/dist/ethers.umd.min.js"></script>

<script src="https://unpkg.com/@walletconnect/modal@2.6.2/dist/index.umd.js"></script>
<script src="https://unpkg.com/@walletconnect/ethereum-provider@2.11.0/dist/index.umd.js"></script>

<style>

body{
background:#0f172a;
display:flex;
justify-content:center;
align-items:center;
height:100vh;
font-family:Arial;
}

.box{
text-align:center;
}

.wallet-btn{

display:block;
width:230px;
margin:12px auto;
padding:14px;
border-radius:10px;
border:none;
font-size:15px;
cursor:pointer;
color:white;

}

.metamask{background:#f6851b;}
.walletconnect{background:#3b82f6;}

</style>
</head>

<body>

<div class="box">

<h2 style="color:white">Connect Wallet</h2>

<button class="wallet-btn metamask" onclick="connectInjected()">
MetaMask / Injected Wallet
</button>

<button class="wallet-btn walletconnect" onclick="connectWalletConnect()">
WalletConnect (300+ wallets)
</button>

</div>

<script>

/* ================= LOGIN FLOW ================= */

async function loginFlow(provider){

try{

const signer = await provider.getSigner()

const address = await signer.getAddress()

const nonceRes = await fetch("./php/wallet-nonce.php",{credentials:"include"})
const nonceData = await nonceRes.json()

if(!nonceData.success){

alert("Nonce error")
return

}

const signature = await signer.signMessage(nonceData.nonce)

const login = await fetch("./php/wallet-auth.php",{

method:"POST",

headers:{
"Content-Type":"application/json"
},

credentials:"include",

body:JSON.stringify({

address:address,
signature:signature

})

})

const result = await login.json()

if(result.success){

window.location="home.html"

}else{

alert(result.message)

}

}catch(e){

console.log(e)

alert("Login failed")

}

}

/* ================= Injected Wallet ================= */

async function connectInjected(){

try{

if(!window.ethereum){

alert("Open site inside wallet browser")

return

}

await window.ethereum.request({
method:"eth_requestAccounts"
})

const provider = new ethers.BrowserProvider(window.ethereum)

await loginFlow(provider)

}catch(e){

console.log(e)
alert("Wallet connection failed")

}

}

/* ================= WalletConnect ================= */

async function connectWalletConnect(){

try{

const provider = await window.WalletConnectEthereumProvider.init({

projectId:"8f3eb30e7e2014e1b7bddef50ae90ae9",

chains:[1],

showQrModal:true,

metadata:{

name:"Ollique",

description:"Web3 Login",

url:window.location.origin,

icons:["https://walletconnect.com/walletconnect-logo.png"]

}

})

await provider.connect()

const ethersProvider = new ethers.BrowserProvider(provider)

await loginFlow(ethersProvider)

}catch(e){

console.log(e)

alert("WalletConnect failed")

}

}

</script>

</body>
</html>