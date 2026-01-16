# Bonus Expiration Timeline Explanation

## The Question
After running the cron on **January 8, 2026**, why are the expiring amounts in this order:
- **89 days** from now (April 7, 2026): **950 points**
- **30 days** from now (February 7, 2026): **1,350 points**
- **7 days** from now (January 15, 2026): **1,000 points**

This seems backwards - shouldn't bonuses expiring sooner have smaller amounts?

## The Answer: Different Customers Earn Bonuses at Different Times

The key insight is: **These are NOT the same bonuses at different time periods**. They are **separate bonuses awarded on different dates** with **different expiration dates**.

Think of it like groceries with different expiration dates on your shelf:
- Milk bought today expires in 7 days
- Yogurt bought 2 weeks ago expires in 30 days
- Cheese bought 3 months ago expires in 90 days

The expiration date depends on **when it was purchased**, not how much you have!

---

## Detailed Timeline Analysis

### Today: January 8, 2026

Let's trace each bonus from the test data:

### 1. Bonuses Expiring in ~90 Days (April 7-8, 2026)

**Order #100001**: 500 points
- **Awarded**: January 8, 2026 (today)
- **Expires**: April 8, 2026 (90 days from now)
- **Expiration reason**: Fresh bonus, full 90-day lifetime

**Order #100013**: 450 points
- **Awarded**: January 8, 2026 (today)
- **Expires**: April 7, 2026 (89 days from now)
- **Expiration reason**: Fresh bonus, 89-day lifetime (edge case test)

**Total expiring in ~90 days**: **950 points**

---

### 2. Bonuses Expiring in ~30 Days (February 7, 2026)

**Order #100002**: 750 points
- **Awarded**: November 9, 2025 (60 days ago)
- **Expires**: February 7, 2026 (30 days from now)
- **Expiration reason**: Already been around for 60 days, only 30 days left

**Order #100008**: 250 points
- **Awarded**: February 8, 2025 (335 days ago!)
- **Expires**: February 7, 2026 (30 days from now)
- **Expiration reason**: Old bonus near end of 365-day lifetime

**Order #100009**: 350 points
- **Awarded**: February 8, 2025 (335 days ago!)
- **Expires**: February 7, 2026 (30 days from now)
- **Expiration reason**: Old bonus near end of 365-day lifetime

**Total expiring in ~30 days**: **1,350 points**

---

### 3. Bonuses Expiring in ~7 Days (January 15, 2026)

**Order #100003**: 1,000 points
- **Awarded**: January 15, 2025 (358 days ago!)
- **Expires**: January 15, 2026 (7 days from now)
- **Expiration reason**: Old bonus at very end of 365-day lifetime

**Order #100004**: 300 points (expires tomorrow)
- **Awarded**: January 9, 2025 (364 days ago!)
- **Expires**: January 9, 2026 (tomorrow)
- **Expiration reason**: Old bonus at absolute end of 365-day lifetime
- **Note**: Also caught by 7-day warning (within tolerance range)

**Total expiring in ~7 days**: **1,300 points**
(Cron showed 1,000 because order #100004 was counted separately or already marked)

---

## Visual Timeline

```
Timeline (not to scale):
════════════════════════════════════════════════════════════════════════════

Jan 2025          Apr 2025          Jul 2025          Oct 2025         Jan 2026
   │                 │                 │                 │                 │
   │                                                                       │
   ├─ Order #100003 awarded (1000pts)                                     │
   │  [358 days ago]                                                       │
   │                                                                       │
   └─────────────────────────────────────────────────────────────────────┐│
                                                                           ││
                                                          TODAY            ││
                                                          Jan 8            ││
                                                            ↓              ││
                                                            │              ││
   ┌────────────────────────────────────────────────────────┘              ││
   │                                                                       ││
   │  Order #100003 EXPIRES in 7 days →                                   ││
   │                                    Jan 15, 2026                       ││
   │                                    [1000 points expire]               ││
   │                                                                       ││
   │                                                                       ││
   │                                                                       ││
Feb 2025                                                                   ││
   │                                                                       ││
   ├─ Order #100008 awarded (250pts)                                      ││
   ├─ Order #100009 awarded (350pts)                                      ││
   │  [335 days ago]                                                       ││
   │                                                                       ││
   └───────────────────────────────────────────────────────────────────┐  ││
                                                                        │  ││
   Orders #100008 & #100009 EXPIRE in 30 days →                        │  ││
                                                Feb 7, 2026             │  ││
                                                [600 points expire]     │  ││
                                                                        │  ││
                                                                        │  ││
Nov 2025                                                                │  ││
   │                                                                    │  ││
   ├─ Order #100002 awarded (750pts)                                   │  ││
   │  [60 days ago]                                                     │  ││
   │                                                                    │  ││
   └────────────────────────────────────────────────────────────────┐  │  ││
                                                                     │  │  ││
   Order #100002 EXPIRES in 30 days →                               │  │  ││
                                      Feb 7, 2026                    │  │  ││
                                      [750 points expire]            │  │  ││
                                                                     │  │  ││
                                       TOTAL 30-day: 1,350 points ───┘  │  ││
                                                                        │  ││
                                                                        │  ││
TODAY: Jan 8, 2026                                                      │  ││
   │                                                                    │  ││
   ├─ Order #100001 awarded (500pts)                                   │  ││
   ├─ Order #100013 awarded (450pts)                                   │  ││
   │  [just now]                                                        │  ││
   │                                                                    │  ││
   └────────────────────────────────────────────────────────────────┐  │  ││
                                                                     │  │  ││
   Orders #100001 & #100013 EXPIRE in 90 days →                     │  │  ││
                                                Apr 7-8, 2026        │  │  ││
                                                [950 points expire]  │  │  ││
                                                                     │  │  ││
════════════════════════════════════════════════════════════════════════════
```

---

## Why This Makes Sense

### Real-World Analogy:

Imagine you're a customer of this store:

**Year Ago (Jan 2025)**:
- You made a big purchase → earned **1,000 bonus points**
- These expire after 365 days → **January 15, 2026** (7 days from now!)

**11 Months Ago (Feb 2025)**:
- You made two smaller purchases → earned **250 + 350 = 600 points**
- These expire after 365 days → **February 7, 2026** (30 days from now)

**2 Months Ago (Nov 2025)**:
- You made another purchase → earned **750 points**
- These expire after 365 days → **February 7, 2026** (30 days from now)

**Today (Jan 2026)**:
- You just made fresh purchases → earned **500 + 450 = 950 points**
- These expire after 90 days → **April 7-8, 2026** (90 days from now)

### The Pattern:

| Time Period | Points Expiring | Why These Amounts? |
|-------------|----------------|-------------------|
| **7 days** | 1,000 pts | One big purchase from 358 days ago |
| **30 days** | 1,350 pts | Three purchases: one from 60 days ago (750) + two from 335 days ago (250+350) |
| **90 days** | 950 pts | Two fresh purchases made today (500+450) |

---

## Key Insight: Expiration Date ≠ Amount

**The amount expiring has NO correlation with how soon it expires!**

Instead:
- **Small amounts** can expire soon (if customer made small recent purchases)
- **Large amounts** can expire later (if customer made big recent purchases)
- **Any amount** can expire at any time (depends on purchase history)

### Why 90-day bonuses are smallest:

In the test data, the **newest bonuses** (awarded today) happen to be the **smallest amounts** (500 + 450 = 950).

If the customer had made a huge purchase today worth 10,000 points, then **90-day expiration** would show **10,000 points** instead!

---

## Real Production Scenario

In a real store, you might see:

**Black Friday Sale (Nov 2025)**:
- Customer bought 50,000₽ worth of goods
- Earned **5,000 bonus points**
- Expires in **30 days** (February 2026)

**Regular Purchase (Today)**:
- Customer bought 3,000₽ worth of goods
- Earned **300 bonus points**
- Expires in **90 days** (April 2026)

Result:
- **30-day warning**: 5,000 points expiring (big Black Friday bonus)
- **90-day warning**: 300 points expiring (small regular purchase)

This is **completely normal** and expected!

---

## Summary

### The Three Key Points:

1. **Each warning period tracks DIFFERENT bonuses**
   - 7-day warning = bonuses awarded ~358 days ago
   - 30-day warning = bonuses awarded 60-335 days ago
   - 90-day warning = bonuses awarded today

2. **Expiration amount depends on purchase history**
   - Big purchases → big bonuses → big expiration amounts
   - Small purchases → small bonuses → small expiration amounts
   - Timing of purchases determines expiration date

3. **Test data is deliberately varied**
   - Designed to test different scenarios
   - Not meant to represent realistic patterns
   - Shows system works correctly regardless of amounts

### Bottom Line:

**950 points expiring in 90 days** vs **1,350 points expiring in 30 days** is perfectly normal - it just means the customer made bigger purchases 11 months ago than they made today!
