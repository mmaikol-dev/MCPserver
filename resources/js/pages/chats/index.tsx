import AppLayout from '@/layouts/app-layout'
import { Head } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'
import { useEffect, useRef, useState } from 'react'
import { Loader2, Send, CheckCircle2, AlertCircle } from 'lucide-react'

type Message = {
    role: 'user' | 'ai'
    content: string
    toolResults?: any[]
}

type GeminiHistoryItem = {
    role: 'user' | 'model' | 'function'
    parts: any[]
}

export default function OrderChat() {
    const [message, setMessage] = useState('')
    const [messages, setMessages] = useState<Message[]>([])
    const [history, setHistory] = useState<GeminiHistoryItem[]>([])
    const [loading, setLoading] = useState(false)
    const bottomRef = useRef<HTMLDivElement>(null)

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
    }, [messages])

    const sendMessage = async () => {
        if (!message.trim() || loading) return

        const userMessage = message.trim()

        // Append user message immediately
        setMessages(prev => [
            ...prev,
            { role: 'user', content: userMessage },
        ])

        setMessage('')
        setLoading(true)

        try {
            const response = await fetch('/chats/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (
                        document.querySelector(
                            'meta[name="csrf-token"]'
                        ) as HTMLMetaElement
                    ).content,
                },
                body: JSON.stringify({
                    message: userMessage,
                    history: history,
                }),
            })

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}))
                throw new Error(errorData.message || 'Request failed')
            }

            const data = await response.json()

            // Update conversation history
            if (data.history) {
                setHistory(data.history)
            }

            // Add AI response
            setMessages(prev => [
                ...prev,
                {
                    role: 'ai',
                    content: data.reply || 'No response received.',
                    toolResults: data.toolResults,
                },
            ])
        } catch (error) {
            console.error('Chat error:', error)
            setMessages(prev => [
                ...prev,
                {
                    role: 'ai',
                    content: `Error: ${error instanceof Error ? error.message : 'Failed to get response. Please try again.'}`,
                },
            ])
        } finally {
            setLoading(false)
        }
    }

    const renderToolResults = (toolResults?: any[]) => {
        if (!toolResults || toolResults.length === 0) return null

        return (
            <div className="mt-3 space-y-2">
                {toolResults.map((result, idx) => {
                    const response = result.functionResponse?.response
                    const hasError = response?.error

                    return (
                        <div
                            key={idx}
                            className={`rounded-md border p-3 text-xs ${hasError
                                    ? 'border-destructive/50 bg-destructive/5'
                                    : 'border-green-500/50 bg-green-500/5'
                                }`}
                        >
                            <div className="flex items-center gap-2 mb-2">
                                {hasError ? (
                                    <AlertCircle className="h-4 w-4 text-destructive" />
                                ) : (
                                    <CheckCircle2 className="h-4 w-4 text-green-600" />
                                )}
                                <Badge variant="outline" className="text-xs">
                                    {result.functionResponse?.name || 'Tool'}
                                </Badge>
                            </div>

                            {hasError ? (
                                <p className="text-destructive">{response.error}</p>
                            ) : (
                                <div className="space-y-1 text-muted-foreground">
                                    {response?.message && (
                                        <p className="font-medium text-foreground">
                                            {response.message}
                                        </p>
                                    )}
                                    {response?.order && (
                                        <div className="mt-2 space-y-1">
                                            <p>Order #{response.order.order_no}</p>
                                            <p>Client: {response.order.client_name}</p>
                                            <p>Amount: {response.order.amount}</p>
                                            <p>Product: {response.order.product_name} (x{response.order.quantity})</p>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )
                })}
            </div>
        )
    }

    return (
        <AppLayout>
            <Head title="AI Order Chat" />

            <div className="flex h-full flex-col rounded-xl border border-sidebar-border bg-background p-4">
                <div className="mb-4 border-b pb-3">
                    <h2 className="text-lg font-semibold">AI Order Assistant</h2>
                    <p className="text-sm text-muted-foreground">
                        Create orders using natural language
                    </p>
                </div>

                <ScrollArea className="flex-1 pr-4">
                    <div className="space-y-4 pb-4">
                        {messages.length === 0 && (
                            <div className="flex h-[400px] items-center justify-center">
                                <div className="text-center space-y-3">
                                    <div className="text-4xl">💬</div>
                                    <h3 className="text-lg font-medium">Start a conversation</h3>
                                    <p className="text-sm text-muted-foreground max-w-md">
                                        Try: "Create an order for John Doe, phone 0712345678,
                                        address 123 Main St, Nairobi, for 2 laptops at 50000 KES each"
                                    </p>
                                </div>
                            </div>
                        )}

                        {messages.map((msg, i) => (
                            <div
                                key={i}
                                className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'
                                    }`}
                            >
                                <div
                                    className={`max-w-[80%] rounded-lg p-3 text-sm ${msg.role === 'user'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted'
                                        }`}
                                >
                                    <div className="whitespace-pre-wrap break-words">
                                        {msg.content}
                                    </div>
                                    {msg.role === 'ai' && renderToolResults(msg.toolResults)}
                                </div>
                            </div>
                        ))}

                        {loading && (
                            <div className="flex justify-start">
                                <div className="max-w-[80%] rounded-lg bg-muted p-3 text-sm">
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                        <span>AI is thinking...</span>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div ref={bottomRef} />
                    </div>
                </ScrollArea>

                <div className="mt-4 flex gap-2">
                    <Input
                        placeholder="Describe the order you want to create..."
                        value={message}
                        onChange={(e) => setMessage(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' && !e.shiftKey) {
                                e.preventDefault()
                                sendMessage()
                            }
                        }}
                        disabled={loading}
                        className="flex-1"
                    />
                    <Button
                        onClick={sendMessage}
                        disabled={loading || !message.trim()}
                        size="icon"
                    >
                        {loading ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <Send className="h-4 w-4" />
                        )}
                    </Button>
                </div>

                <p className="mt-2 text-xs text-muted-foreground text-center">
                    Press Enter to send • Shift+Enter for new line
                </p>
            </div>
        </AppLayout>
    )
}