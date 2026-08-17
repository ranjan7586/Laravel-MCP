import os
from typing import Annotated, TypedDict

from dotenv import load_dotenv
from langchain_core.messages import BaseMessage, SystemMessage
from langchain_groq import ChatGroq
from langchain_mcp_adapters.client import MultiServerMCPClient
from langgraph.checkpoint.memory import MemorySaver
from langgraph.graph import END, START, StateGraph
from langgraph.graph.message import add_messages
from langgraph.prebuilt import ToolNode, tools_condition

load_dotenv()

MCP_WEATHER_URL = "http://127.0.0.1:8000/mcp/weather"

SYSTEM_PROMPT = SystemMessage(content="""\
You are a friendly assistant with access to a live weather tool.

- If the user is just making conversation (greetings, small talk, general
  questions), reply normally in plain text and don't use any tool.
- If the user asks about the weather but hasn't given a location, ask them
  which city or place they mean before doing anything else. Don't guess a
  location.
- Once you know the location, call the get-weather tool to fetch live data,
  then summarize the result in a short, natural sentence (temperature,
  condition, and how it feels). Never make weather data up yourself.
""")


class ChatState(TypedDict):
    messages: Annotated[list[BaseMessage], add_messages]


class ChatController:
    """Owns the LangGraph agent: MCP tool connection, node wiring, and
    per-session conversation memory."""

    def __init__(self) -> None:
        self.mcp_client = MultiServerMCPClient(
            {
                "weather": {
                    "url": MCP_WEATHER_URL,
                    "transport": "streamable_http",
                }
            }
        )
        self.llm = ChatGroq(
            api_key=os.environ["GROQ_API_KEY"],
            model="openai/gpt-oss-120b",
            temperature=0,
        )
        self.checkpointer = MemorySaver()
        self.graph = None

    async def setup(self) -> None:
        """Load MCP tools and compile the graph. Call once on app startup."""
        tools = await self.mcp_client.get_tools()
        llm_with_tools = self.llm.bind_tools(tools)

        async def chat_node(state: ChatState) -> dict:
            messages = [SYSTEM_PROMPT, *state["messages"]]
            response = await llm_with_tools.ainvoke(messages)
            return {"messages": [response]}

        builder = StateGraph(ChatState)
        builder.add_node("chat_node", chat_node)
        builder.add_node("tools", ToolNode(tools))
        builder.add_edge(START, "chat_node")
        builder.add_conditional_edges("chat_node", tools_condition, {"tools": "tools", END: END})
        builder.add_edge("tools", "chat_node")

        self.graph = builder.compile(checkpointer=self.checkpointer)

    async def chat(self, session_id: str, message: str) -> str:
        if self.graph is None:
            raise RuntimeError("ChatController.setup() must be awaited before chat()")

        config = {"configurable": {"thread_id": session_id}}
        result = await self.graph.ainvoke(
            {"messages": [{"role": "user", "content": message}]},
            config=config,
        )
        return result["messages"][-1].content
