const { render, createElement: el } = wp.element;
const { Button, PanelBody, Card, CardBody, CardHeader, CardFooter, TabPanel } = wp.components;

function App() {
    return el(Card, {},
        el(CardHeader, {}, "Content actions"),
        el(CardBody, {},
            el(TabPanel, {children: (x) => console.log(x), tabs: [{name: "translate", title: "Translate"}, {name: "clone", title: "Clone"}]}),
        ),
        el(CardFooter, {},
            el(Button, { isPrimary: true }, "Click Me"),
        )
    );
}

render(
    el(App),
    document.getElementById("smartling-app")
);
