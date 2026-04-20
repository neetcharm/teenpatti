# 🎮 Teen Patti - Convex Integration Guide

This guide explains how to integrate your Game Server (Laravel) with your Tenant Backend (**Convex**).

## 🧩 Architecture Overview
1. **Convex Action**: Requests a new game session from Laravel.
2. **Android/Web Client**: Launches the `game_url` in a WebView.
3. **Convex HTTP Action**: Receives webhooks from Laravel to manage user balances (Bet/Win).

---

## 🛠️ Step 1: Create Game Session (Convex Action)

Create a file `convex/gameApi.ts`. This action generates the HMAC signature and calls the Laravel API to get a launch URL.

```typescript
import { action } from "./_generated/server";
import { v } from "convex/values";
import crypto from "crypto";

export const startSession = action({
  args: { 
    playerId: v.string(), 
    playerName: v.string() 
  },
  handler: async (ctx, args) => {
    // 💡 Recommendation: Use Environment Variables in Convex Dashboard
    const API_KEY = process.env.GAME_API_KEY; 
    const API_SECRET = process.env.GAME_API_SECRET;
    const BASE_URL = "https://game.tikkix.com";

    const payload = {
      player_id: args.playerId,
      player_name: args.playerName,
      game_id: "teen_patti",
      currency: "INR"
    };

    const body = JSON.stringify(payload);

    // 🔐 Generate HMAC-SHA256 Signature
    const signature = crypto
      .createHmac("sha256", API_SECRET!)
      .update(body)
      .digest("hex");

    // 🚀 Call Laravel API
    const response = await fetch(`${BASE_URL}/api/v1/session/create`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-API-Key": API_KEY!,
        "X-Signature": signature
      },
      body
    });

    const result = await response.json();
    
    // Result contains: { "game_url": "http://.../launch/{token}" }
    return result;
  }
});
```

---

## ⚓ Step 2: Handle Webhooks (Convex HTTP Action)

Create or update `convex/http.ts`. This endpoint listens for balance checks, debits (bets), and credits (wins).

```typescript
import { httpAction } from "./_generated/server";
import { httpRouter } from "convex/server";
import crypto from "crypto";

const http = httpRouter();

http.route({
  path: "/webhook",
  method: "POST",
  handler: httpAction(async (ctx, request) => {
    const body = await request.json();
    const WEBHOOK_SECRET = process.env.GAME_WEBHOOK_SECRET;

    // 🛡️ 1. Verify Signature
    // Expected Payload: action|player_id|amount|round_id|timestamp
    const payloadParts = [
      body.action,
      body.player_id,
      body.amount ?? "",
      body.round_id ?? "",
      body.timestamp
    ];
    const dataToSign = payloadParts.join("|");

    const expectedSignature = crypto
      .createHmac("sha256", WEBHOOK_SECRET!)
      .update(dataToSign)
      .digest("hex");

    if (body.signature !== expectedSignature) {
      return new Response("Unauthorized Signature", { status: 401 });
    }

    // ⚙️ 2. Route Actions
    switch (body.action) {
      case "balance":
        // TODO: Query your Convex DB for real balance
        return new Response(JSON.stringify({ 
            status: "ok", 
            balance: 5000.00 
        }));

      case "debit":
        // TODO: Subtract body.amount from user balance in DB
        return new Response(JSON.stringify({ 
          status: "ok", 
          balance: 4500.00, 
          transaction_id: `DR_${Date.now()}` 
        }));

      case "credit":
        // TODO: Add body.amount to user balance in DB
        return new Response(JSON.stringify({ 
          status: "ok", 
          balance: 5400.00, 
          transaction_id: `CR_${Date.now()}` 
        }));
    }

    return new Response("Unknown Action", { status: 400 });
  }),
});

export default http;
```

---

## 📲 Step 3: Launch in WebView (Android)

Once your Convex Action returns the `game_url`, load it in your Android app:

```java
WebView webView = findViewById(R.id.gameWebView);
webView.getSettings().setJavaScriptEnabled(true);
webView.getSettings().setDomStorageEnabled(true);
webView.loadUrl(gameUrlFromConvex);
```

---

## 📝 Configuration Checklist

1. **In Laravel Admin Panel**:
   - Create a Tenant.
   - Set **Webhook URL** to: `https://your-project.convex.site/webhook`.
   - Copy the **API Key**, **API Secret**, and **Webhook Secret**.

2. **In Convex Dashboard**:
   - Go to **Settings > Environment Variables**.
   - Add `GAME_API_KEY`, `GAME_API_SECRET`, and `GAME_WEBHOOK_SECRET`.

3. **In Convex CLI**:
   - Run `npx convex deploy` to make your HTTP routes live.
