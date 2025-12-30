import React from 'react';
import { Truck, MessageSquare, Mic, Sparkles, Package, BarChart3, Clock, Shield } from 'lucide-react';

export default function Welcome({ canRegister = true }) {
    const auth = { user: null }; // Replace with actual auth from usePage().props

    return (
        <div className="min-h-screen bg-background text-foreground">
            {/* Navigation */}
            <nav className="border-b">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between items-center h-16">
                        <div className="flex items-center space-x-3">
                            <div className="bg-primary p-2 rounded-lg">
                                <Truck className="w-6 h-6 text-primary-foreground" />
                            </div>
                            <span className="text-xl font-bold">
                                RealDeal Logistics
                            </span>
                        </div>

                        {auth.user ? (
                            <a href="/dashboard" className="px-6 py-2 bg-primary text-primary-foreground rounded-lg font-semibold hover:bg-primary/90 transition-colors">
                                Dashboard
                            </a>
                        ) : (
                            <div className="flex items-center space-x-4">
                                <a href="/login" className="px-6 py-2 text-muted-foreground hover:text-foreground transition-colors font-medium">
                                    Log in
                                </a>
                                {canRegister && (
                                    <a href="/register" className="px-6 py-2 bg-primary text-primary-foreground rounded-lg font-semibold hover:bg-primary/90 transition-colors">
                                        Get Started
                                    </a>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </nav>

            {/* Hero Section */}
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-32">
                <div className="text-center space-y-8">
                    <div className="inline-flex items-center space-x-2 bg-muted border rounded-full px-4 py-2">
                        <Sparkles className="w-4 h-4" />
                        <span className="text-sm font-medium">AI-Powered MCP System</span>
                    </div>

                    <h1 className="text-6xl md:text-7xl font-bold leading-tight">
                        Effortless Order
                        <br />
                        Management with AI
                    </h1>

                    <p className="text-xl text-muted-foreground max-w-3xl mx-auto leading-relaxed">
                        Transform your logistics operations with RealDeal's intelligent MCP system.
                        Manage orders accurately with our AI assistant and voice-to-text technology.
                    </p>

                    <div className="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
                        <a href="/register" className="group px-8 py-4 bg-primary text-primary-foreground rounded-lg font-semibold text-lg hover:bg-primary/90 transition-colors flex items-center space-x-2">
                            <span>Start Free Trial</span>
                            <Sparkles className="w-5 h-5 group-hover:rotate-12 transition-transform" />
                        </a>
                        <a href="#features" className="px-8 py-4 border rounded-lg font-semibold text-lg hover:bg-accent transition-colors flex items-center space-x-2">
                            <span>Learn More</span>
                        </a>
                    </div>
                </div>

                {/* Hero Demo */}
                <div className="mt-20">
                    <div className="border rounded-xl p-8 bg-card shadow-lg">
                        <div className="bg-muted/50 rounded-lg p-6 space-y-4">
                            <div className="flex items-center space-x-3">
                                <MessageSquare className="w-6 h-6" />
                                <span className="text-sm font-medium">AI Assistant Active</span>
                                <div className="flex-1"></div>
                                <div className="flex items-center space-x-2">
                                    <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                    <span className="text-xs text-muted-foreground">Live</span>
                                </div>
                            </div>
                            <div className="bg-accent border rounded-lg p-4">
                                <p className="text-sm">"Show me all pending orders for delivery today"</p>
                            </div>
                            <div className="bg-muted rounded-lg p-4 space-y-2">
                                <p className="text-sm">Found 23 pending orders for today's delivery. Here's the summary:</p>
                                <div className="grid grid-cols-3 gap-3 pt-2">
                                    <div className="bg-accent rounded-lg p-3 text-center border">
                                        <p className="text-2xl font-bold">23</p>
                                        <p className="text-xs text-muted-foreground">Orders</p>
                                    </div>
                                    <div className="bg-accent rounded-lg p-3 text-center border">
                                        <p className="text-2xl font-bold">18</p>
                                        <p className="text-xs text-muted-foreground">In Transit</p>
                                    </div>
                                    <div className="bg-accent rounded-lg p-3 text-center border">
                                        <p className="text-2xl font-bold">5</p>
                                        <p className="text-xs text-muted-foreground">Ready</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Features Section */}
            <div id="features" className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 bg-muted/30">
                <div className="text-center mb-16">
                    <h2 className="text-4xl md:text-5xl font-bold mb-4">
                        Powerful Features
                    </h2>
                    <p className="text-xl text-muted-foreground">Everything you need to manage logistics intelligently</p>
                </div>

                <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    {/* AI Assistant */}
                    <div className="group bg-card rounded-xl border p-8 hover:shadow-lg transition-all duration-300 hover:border-primary">
                        <div className="bg-primary text-primary-foreground w-14 h-14 rounded-lg flex items-center justify-center mb-6">
                            <MessageSquare className="w-7 h-7" />
                        </div>
                        <h3 className="text-2xl font-bold mb-3">AI Assistant</h3>
                        <p className="text-muted-foreground leading-relaxed">
                            Intelligent conversational AI that understands natural language and helps manage orders with precision and speed.
                        </p>
                    </div>

                    {/* Voice to Text */}
                    <div className="group bg-card rounded-xl border p-8 hover:shadow-lg transition-all duration-300 hover:border-primary">
                        <div className="bg-primary text-primary-foreground w-14 h-14 rounded-lg flex items-center justify-center mb-6">
                            <Mic className="w-7 h-7" />
                        </div>
                        <h3 className="text-2xl font-bold mb-3">Voice to Text</h3>
                        <p className="text-muted-foreground leading-relaxed">
                            Hands-free order management with accurate voice recognition. Create and update orders while on the move.
                        </p>
                    </div>

                    {/* Order Tracking */}
                    <div className="group bg-card rounded-xl border p-8 hover:shadow-lg transition-all duration-300 hover:border-primary">
                        <div className="bg-primary text-primary-foreground w-14 h-14 rounded-lg flex items-center justify-center mb-6">
                            <Package className="w-7 h-7" />
                        </div>
                        <h3 className="text-2xl font-bold mb-3">Smart Tracking</h3>
                        <p className="text-muted-foreground leading-relaxed">
                            Real-time order tracking with AI-powered predictions and automated notifications for all stakeholders.
                        </p>
                    </div>

                    {/* Analytics */}
                    <div className="group bg-card rounded-xl border p-8 hover:shadow-lg transition-all duration-300 hover:border-primary">
                        <div className="bg-primary text-primary-foreground w-14 h-14 rounded-lg flex items-center justify-center mb-6">
                            <BarChart3 className="w-7 h-7" />
                        </div>
                        <h3 className="text-2xl font-bold mb-3">AI Analytics</h3>
                        <p className="text-muted-foreground leading-relaxed">
                            Deep insights and predictive analytics to optimize routes, reduce costs, and improve delivery times.
                        </p>
                    </div>

                    {/* Real-time Updates */}
                    <div className="group bg-card rounded-xl border p-8 hover:shadow-lg transition-all duration-300 hover:border-primary">
                        <div className="bg-primary text-primary-foreground w-14 h-14 rounded-lg flex items-center justify-center mb-6">
                            <Clock className="w-7 h-7" />
                        </div>
                        <h3 className="text-2xl font-bold mb-3">Real-time Updates</h3>
                        <p className="text-muted-foreground leading-relaxed">
                            Instant synchronization across all devices with live updates on order status, delays, and deliveries.
                        </p>
                    </div>

                    {/* Security */}
                    <div className="group bg-card rounded-xl border p-8 hover:shadow-lg transition-all duration-300 hover:border-primary">
                        <div className="bg-primary text-primary-foreground w-14 h-14 rounded-lg flex items-center justify-center mb-6">
                            <Shield className="w-7 h-7" />
                        </div>
                        <h3 className="text-2xl font-bold mb-3">Enterprise Security</h3>
                        <p className="text-muted-foreground leading-relaxed">
                            Bank-level encryption and compliance with industry standards to keep your data safe and secure.
                        </p>
                    </div>
                </div>
            </div>

            {/* CTA Section */}
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                <div className="bg-muted rounded-xl border p-12 md:p-16 text-center">
                    <h2 className="text-4xl md:text-5xl font-bold mb-6">
                        Ready to Transform Your Logistics?
                    </h2>
                    <p className="text-xl text-muted-foreground mb-8 max-w-2xl mx-auto">
                        Join hundreds of companies using RealDeal Logistics MCP to streamline their operations with AI.
                    </p>
                    <a href="/register" className="inline-flex items-center space-x-2 px-8 py-4 bg-primary text-primary-foreground rounded-lg font-semibold text-lg hover:bg-primary/90 transition-colors">
                        <span>Get Started Free</span>
                        <Sparkles className="w-5 h-5" />
                    </a>
                </div>
            </div>

            {/* Footer */}
            <footer className="border-t">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div className="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                        <div className="flex items-center space-x-3">
                            <div className="bg-primary p-2 rounded-lg">
                                <Truck className="w-5 h-5 text-primary-foreground" />
                            </div>
                            <span className="font-bold">RealDeal Logistics MCP</span>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            © 2025 RealDeal Logistics. All rights reserved.
                        </p>
                    </div>
                </div>
            </footer>
        </div>
    );
}